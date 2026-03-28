<?php

declare(strict_types=1);

namespace App\Chat\ContextHandlers;

use App\Chat\Condition\ArrayMorphedEvaluable;
use App\Chat\Condition\Expression;
use App\Chat\Contexts\Context;
use App\Chat\Contexts\UpdateContext;
use App\Chat\Contracts\ContextHandler;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerAwareInterface;
use Werty\Http\Clients\TelegramBot\Types\Update;

abstract class BasicContextHandler implements ContextHandler {
    protected array $handlers = [];

    public function when(ArrayMorphedEvaluable $condition, array $callback, int $priority = self::PRIORITY_CONTEXTUAL, ?string $key = null, array $params = []): void
    {
        if ($key === null) {
            $this->handlers[] = [$priority, $condition, $callback, $params];
        } else {
            $this->handlers[$key] = [$priority, $condition, $callback, $params];
        }
    }

    public function removeHandler(string $key): void
    {
        unset($this->handlers[$key]);
    }
    public function getHandlers(int $priority): array
    {
        return array_filter($this->handlers, fn($handler) => $handler[0] === $priority);
    }
};
