<?php

declare(strict_types=1);

namespace App\Chat\Contexts;

use App\Chat\Contracts\ContextHandler;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Types\Update;

interface Context
{
    public function setup(array $params = []): void;
    public function teardown(): void;

    public function run(string $method, array $params = []): ?ContextHandler;
}
