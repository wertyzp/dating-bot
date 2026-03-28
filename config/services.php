<?php

declare(strict_types=1);

return [
    'reddit' => [
        'client_id' => env('REDDIT_CLIENT_ID'),
        'client_secret' => env('REDDIT_CLIENT_SECRET'),
        'username' => env('REDDIT_USERNAME'),
        'password' => env('REDDIT_PASSWORD'),
        'user_agent' => env('REDDIT_USER_AGENT'),
    ],
];
