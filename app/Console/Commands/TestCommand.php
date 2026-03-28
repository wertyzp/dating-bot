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
use App\Chat\Support\UpdateClient;
use App\Models\Link;
use App\Models\Employee;
use App\Services\LinkService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Exceptions\HttpException;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Types\MessageEntity;
use Werty\Http\Clients\TelegramBot\Types\ParseMode;
use Werty\Http\Clients\TelegramBot\Types\Update;
use Werty\Http\Clients\TelegramBot\Types\User;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Command';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        // get link from arguments
        $link = $this->ask('Enter link');

        $service = new \App\Chat\Services\RedditService();
        $result = $service->isLinkAccessible($link);

        var_dump($result);

        $this->info('Command executed successfully.');

    }

    public function updateDaily(int $chatId, string $type): void
    {
        $client = new Client(
            config('telegram.bot_token'),
        );
        $uc = new UpdateClient(
            new Update([
                'update_id' => 0,
                'message' => [
                    'message_id' => 0,
                    'from' => [
                        'id' => 0,
                        'is_bot' => false,
                        'first_name' => '',
                        'last_name' => '',
                        'username' => '',
                        'language_code' => '',
                    ],
                    'chat' => [
                        'id' => $chatId,
                        'type' => 'supergroup',
                    ],
                    'date' => time(),
                    'text' => '',
                ],
            ]),
            $client
        );
        $logger = new Logger('telegram');
        // add stdout handler
        $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', Logger::DEBUG));
        $service = new LinkService(
            $uc,
            $logger,
        );
        $service->updateDailyLinksMessage(
            $chatId,
            $type,
        );
    }

}
