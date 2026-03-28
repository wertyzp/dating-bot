<?php

declare(strict_types=1);

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'bot_tokens' => [
        env('TELEGRAM_BOT_TOKEN'),
    ],
    'commands' => [
        \Werty\Http\Clients\TelegramBot\Types\BotCommandScopeAllPrivateChats::class => [
            ['command' => 'help', 'description' => 'Show some help'],
        ],
    ]
];
