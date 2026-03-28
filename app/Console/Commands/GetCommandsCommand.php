<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Requests\GetMyCommands;
use Werty\Http\Clients\TelegramBot\Types\BotCommandScopeAllPrivateChats;

class GetCommandsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:get-my-commands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get telegram bot commands';

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

        $getMyCommands = new GetMyCommands();
        $getMyCommands->setScope(new BotCommandScopeAllPrivateChats());
        $client = new Client($token);
        $result = $client->getMyCommands($getMyCommands);
        print_r($result);

        return 0;
    }
}
