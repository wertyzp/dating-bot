<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Requests\SetMyCommands;
use Werty\Http\Clients\TelegramBot\Types\BotCommand;
use Werty\Http\Clients\TelegramBot\Types\BotCommandScope;
use Werty\Http\Clients\TelegramBot\Types\BotCommandScopeAllPrivateChats;

class SetMyCommandsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-my-commands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set telegram bot commands';

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

        $client = new Client($token);
        $me = $client->getMe();
        $username = $me->getUsername();
        Cache::put('bot-username', $username);

        $setMyCommands = new SetMyCommands();
        $commandsSource = config('telegram.commands');

        foreach ($commandsSource as $scope => $commandList) {
            if (!is_subclass_of($scope, BotCommandScope::class)) {
                $this->error('Invalid scope: '.$scope);
                continue;
            }
            $commands = [];
            foreach  ($commandList as $command) {
                $commands[] = new BotCommand($command);
            }
            $setMyCommands->setCommands($commands);
            $setMyCommands->setScope(new $scope());
            print_r($setMyCommands);
            $result = $client->setMyCommands($setMyCommands);
            print_r($result);
        }

        return 0;
    }
}
