<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Requests\GetChat;

class GetChatCommand extends Command
{
    /**
     * The name and signature of the console command.
     * optional
     * @var string
     */
    protected $signature = 'telegram:get-chat {chat_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get chat info';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $token = config('telegram.bot_token');

        if (empty($token)) {
            $this->error('telegram.bot_token is required in config');

            return 1;
        }

        $chatId = $this->argument('chat_id');
        if (empty($chatId)) {
            $chatId = (int)$this->ask('Chat Id');
        }
        $this->info('getting webhook info');
        $client = new Client($token);
        $result = $client->getChat(GetChat::create($chatId));
        $this->info(var_export($result, true));

        return 0;
    }
}
