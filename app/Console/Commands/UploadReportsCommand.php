<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Chat\Container;
use App\Chat\ContextManager;
use App\Chat\Debug;
use App\Chat\Exports\StatsExport;
use App\Chat\Handler;
use App\Chat\Handler as ChatHandler;
use App\Chat\HandlerFactory;
use App\Chat\Router;
use App\Models\Chat;
use App\Models\ChatSetup;
use App\Models\DailyMessage;
use App\Models\Employee;
use Google\Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Exceptions\HttpException;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Types\MessageEntity;
use Werty\Http\Clients\TelegramBot\Types\ParseMode;
use Werty\Http\Clients\TelegramBot\Types\Update;
use Werty\Http\Clients\TelegramBot\Types\User;

class UploadReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:upload-reports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload reports to google drive folder';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /** @var Collection<ChatSetup> $chatSetups */
        $chatSetups = ChatSetup::all();

        $this->info("Total chat setups: " . $chatSetups->count());
        foreach ($chatSetups as $chatSetup) {
            if ($chatSetup->employees()->count() === 0) {
                $this->info("No employees for chat setup: {$chatSetup->chat->title}");
                continue;
            }

            $this->info("Uploading report for: {$chatSetup->chat->title}");
            $this->uploadReport($chatSetup);
        }
        return;
    }

    protected function uploadReport(ChatSetup $chatSetup): void
    {
        $chat = $chatSetup->chat;
        $chatTitle = $chat->title;

        // 3 weeks before today
        $from =  (new \DateTimeImmutable())->modify('-3 weeks');
        $to = new \DateTimeImmutable();
        // add 1 day
        $to = $to->modify('+1 day');
        $dateStr = $from->format('Y-m-d') . ' - ' . $to->format('Y-m-d');
        $caption = "$chatTitle Employees report $dateStr";
        // escape path incompatible characters
        $shellCaption =  preg_replace('/[\/\\\:\*\?\"\<\>\|]/', '_', $caption);
        $reportPath = "$shellCaption.xlsx";

        Log::info('Report path', ['path' => $reportPath]);
        try {
            Log::info('Report path', ['path' => $reportPath]);
            Excel::store(new StatsExport($chat->id, $from, $to), $reportPath, 'local');
            $this->upload($chatSetup, $reportPath, storage_path("app/$reportPath"));
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            $this->info("Spreadsheet error: $e");
        } catch (\Throwable $e) {
            Log::error("Error uploading file: $e");
            return;
        }
    }

    /**
     * @throws Exception
     * @throws \Google\Service\Exception
     */
    protected function upload(ChatSetup $chatSetup, string $filename, string $path): void
    {
        $client = new \Google_Client();
        $config = json_decode(base64_decode(env('GOOGLE_AUTH_CONFIG')), true);
        $client->setAuthConfig($config);
        $client->addScope(\Google_Service_Drive::DRIVE);
        $client->useApplicationDefaultCredentials();
        $customId = "employees-report-{$chatSetup->chat_id}";
        $driveService = new \Google_Service_Drive($client);

// find if file already exists
        $query = "appProperties has { key='custom_id' and value='$customId' }";
        $files = $driveService->files->listFiles([
            'q' => $query,
            'fields' => 'files(id, name)',
            'spaces' => 'drive'
        ]);

        $sharedFolderId = env('GOOGLE_DRIVE_FOLDER_ID');


        $content = file_get_contents($path);
        if (!empty($files->getFiles())) {
            $fileList = $files->getFiles();
            $file = reset($fileList);
            $fileMetadata = new \Google_Service_Drive_DriveFile([
                'name' => $filename,
                'appProperties' => [
                    'custom_id' => $customId,
                ]
            ]);
            $driveFile = $driveService->files->update(
                $file->id,
                $fileMetadata,
                [
                    'data' => $content,
                    'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'uploadType' => 'multipart',
                    'fields' => 'id'
                ]
            );
            $this->info("Uploaded File ID: " . $driveFile->id);
        } else {
            $fileMetadata = new \Google_Service_Drive_DriveFile([
                'name' => $filename,
                'parents' => [$sharedFolderId], // This puts the file in the shared folder
                'appProperties' => [
                    'custom_id' => $customId,
                ]
            ]);
            $driveFile = $driveService->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);

            $this->info("Uploaded File ID: " . $driveFile->id);
        }
    }

}
