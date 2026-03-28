<?php

declare(strict_types=1);

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'bot_tokens' => [
        env('TELEGRAM_BOT_TOKEN'),
    ],
    'commands' => [
        \Werty\Http\Clients\TelegramBot\Types\BotCommandScopeAllPrivateChats::class => [
            ['command' => 'reset', 'description' => 'Warning! deletes all bot stored data'],
            ['command' => 'map', 'description' => 'Show message forwarding map'],
            ['command' => 'schedule', 'description' => 'Configure schedule'],
        ],
        \Werty\Http\Clients\TelegramBot\Types\BotCommandScopeAllChatAdministrators::class => [
            ['command' => 'source', 'description' => 'Set group as source'],
            ['command' => 'dest', 'description' => 'Select source for this group'],
        ],
    ]
];
