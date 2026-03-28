<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Chat\Container;
use App\Chat\ContextManager;
use App\Chat\Debug;
use App\Chat\Handler;
use App\Chat\Handler as ChatHandler;
use App\Chat\HandlerFactory;
use App\Chat\Router;
use App\Models\ChatSetup;
use App\Models\DailyMessage;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Exceptions\HttpException;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Types\MessageEntity;
use Werty\Http\Clients\TelegramBot\Types\ParseMode;
use Werty\Http\Clients\TelegramBot\Types\Update;
use Werty\Http\Clients\TelegramBot\Types\User;

class CheckDailyActionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-daily-actions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check employee daily actions';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Checking employee daily actions...');
        // echo curent date
        $this->info(date('Y-m-d H:i:s'));
        $client = new Client(config('telegram.bot_token'));
        /**
         * @var Collection<Employee> $employees
         */
        $employees = Employee::all();
        $now = new \DateTimeImmutable();
        $nowH = (int)$now->format('H');

        foreach ($employees as $employee) {
            $start = $employee->start_time;
            $startH = (int)$start->format('H');

            if ($nowH !== $startH) {
                continue;
            }

            $expectedTypes = [
                DailyMessage::TYPE_ANNOUNCEMENT,
                DailyMessage::TYPE_SUPPORT,
            ];

            $this->info("Employee {$employee->user->getName()} has started work now");

            foreach ($expectedTypes as $type) {
                $message = DailyMessage::query()
                    ->where('daily_messages.chat_id', $employee->chat_id)
                    ->where('daily_messages.type', $type)
                    ->where('daily_messages.created_at', '>=', $now->modify('-1 day'))
                    // join messages on message_id, chat_id
                    ->join('messages', 'messages.message_id', '=', 'daily_messages.message_id')
                    ->where('messages.chat_id', $employee->chat_id)
                    ->where('messages.user_id', $employee->user_id)
                    ->first();
                if ($message) {
                    $this->info("Employee {$employee->user->getName()} has sent daily message");
                    continue;
                }
                $workWarnings = $employee->work_warnings;
                $lastWarningDate = $workWarnings[$type] ? new \DateTimeImmutable($employee->work_warnings[$type]) : null;

                if ($lastWarningDate && $lastWarningDate->format('Y-m-d') === $now->format('Y-m-d')) {
                    $this->info("Employee {$employee->user->getName()} has already received a warning for $type today");
                    continue;
                }

                if ($this->sendWarningMessage($client, $employee, $type)) {
                    $workWarnings[$type] = $now->format("Y-m-d H:i:s");
                    $employee->work_warnings = $workWarnings;
                    $employee->save();
                }
            }
        }
        return;
    }

    private function sendWarningMessage(Client $client, Employee $employee, string $type): bool
    {
        $message = new SendMessage();
        $message->setChatId($employee->chat_id);
        $name = $employee->user->getName();
        $entity = new MessageEntity();
        $entity->setType('mention');
        $entity->setOffset(0);
        $entity->setLength(strlen($name));
        $entity->setUser(new User(['id' => $employee->user_id]));

        $prefix = "$name, your new shift has started, but you didn't sent";
        $bold = new MessageEntity();
        $bold->setType('bold');
        $bold->setOffset(mb_strlen($prefix));
        $bold->setLength(mb_strlen("daily $type"));
        $message->setEntities([$entity, $bold]);
        $message->setText("$prefix daily $type yet after last shift yet");

        try {
            $client->sendMessage($message);
            return true;
        } catch (HttpException $e) {
            $msg = "Error sending warning message about $type to {$employee->user->getName()} (chatId: {$employee->chat_id})";
            Log::error("$msg: $e");
            return false;
        }
    }

}
