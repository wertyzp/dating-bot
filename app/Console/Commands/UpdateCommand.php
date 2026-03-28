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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Exceptions\HttpException;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Types\ParseMode;
use Werty\Http\Clients\TelegramBot\Types\Update;

class UpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:update {--bot-key=} {--input=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process update from telegram bot';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    protected Client $client;

    public function handle()
    {
        $botKey = $this->option('bot-key');
        if (!$botKey) {
            $botKey = 0;
        }
        Log::info('Bot key: '.$botKey);
        $botTokens = config('telegram.bot_tokens');
        $botToken = config('telegram.bot_token');
        if (isset($botTokens[$botKey])) {
            $botToken = $botTokens[$botKey];
        }
        // update config to the selected token
        config(['telegram.bot_token' => $botToken]);

        Log::info('Processing update');
        if ($this->hasOption('input')) {
            $data = $this->option('input');
        } else {
            $data = file_get_contents('php://stdin');
        }
        /**
         * @var ChatHandler $handler
         */
        $update = new Update(json_decode($data));
        $handler = new Handler(new ContextManager(new Container()));

        try {
            $handler->handle($update);
        } catch (HttpException $e) {
            $request = $e->getRequest();
            $response = $e->getResponse();
            Log::debug('Request: '.var_export($request, true));
            Log::debug('Response: '.var_export($response->toArray(), true));
        } catch (\Throwable $e) {
            Log::error("$e");

        }
    }

}
