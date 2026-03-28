<?php

declare(strict_types=1);

namespace App\Chat\Contracts;

use App\Chat\Condition\ArrayMorphedEvaluable;
use App\Chat\Condition\Evaluable;
use App\Chat\Contexts\Context;
use Werty\Http\Clients\TelegramBot\Types\Update;

interface ContextHandler
{
    public const PRIORITY_START = 1;
    public const PRIORITY_COMMANDS = 2;
    public const PRIORITY_GLOBAL = 3;
    public const PRIORITY_CONTEXTUAL = 4;

    public const SEQUENCE = [
        self::PRIORITY_START,
        self::PRIORITY_COMMANDS,
        self::PRIORITY_GLOBAL,
        self::PRIORITY_CONTEXTUAL,
    ];

    public function when(ArrayMorphedEvaluable $condition, array $callback, int $priority = self::PRIORITY_CONTEXTUAL, ?string $key = null, array $params = []): void;
    public function removeHandler(string $key): void;
    /**
     * @return array<array{Evaluable, array}>
     */
    public function getHandlers(int $priority): array;
}
