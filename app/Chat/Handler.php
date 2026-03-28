<?php

declare(strict_types=1);

namespace App\Chat;

use App\Chat\Condition\Evaluable;
use App\Chat\Contexts\Context;
use App\Chat\Contracts\ContextHandler;
use Illuminate\Support\Facades\Log;
use Werty\Http\Clients\TelegramBot\Types\Update;

class Handler
{

    protected array $conditions = [];
    protected array $contextHandlers = [];
    protected ContextHandler $bodyContextHandler;
    protected ContextHandler $fallbackContextHandler;

    public function __construct(protected ContextManager $contextManager)
    {
        $this->bodyContextHandler = new ContextHandlers\UpdateContextHandler();
        $this->fallbackContextHandler = new ContextHandlers\FallbackContextHandler();
    }

    public function pushResolver(ContextHandler $contextHandler): void
    {
        $this->contextHandlers[] = $contextHandler;
    }

    protected function handleHandler(ContextHandler $resolver, Update $update): bool
    {
        $sequence = ContextHandler::SEQUENCE;
        foreach ($sequence as $priority) {
            $handlers = $resolver->getHandlers($priority);
            foreach ($handlers as [,$condition, $callback, $params]) {
                /** @var Evaluable $condition */
                if (!$condition->evaluate($update->toArray())) {
                    continue;
                }
                $newHandler = $this->call($callback, $update, $params);
                // if in process of calling handler we've got new resolver
                // recursively call handleResolver with new resolver
                if ($newHandler !== null) {
                    $this->handleHandler($newHandler, $update);
                }
                return true;

            }
        }
        return false;
    }

    public function handle(Update $update): void
    {
        $handlers = [
            $this->bodyContextHandler,
            ...$this->contextHandlers,
            $this->fallbackContextHandler
        ];
        foreach ($handlers as $handler) {
            $result = $this->handleHandler($handler, $update);
            if ($result) {
                return;
            }
        }

        Debug::message("404 can't find where to route");
        // here we will come for update null session and no condition matched
    }

    protected function call(array $callback, Update $update, array $params): ?ContextHandler
    {
        [$class, $method] = $callback;
        if (!is_subclass_of($class, Context::class)) {
            throw new \RuntimeException(sprintf('Handler callback class %s must implement %s', $class, Context::class));
        }

        $object = $this->contextManager->getContext($class, $update);

        try {
            return $object->run($method, $params);
        } catch (Contexts\Exceptions\Exception $e) {
            $this->contextManager->reportContextException($e);
        }
        return null;
    }

}
