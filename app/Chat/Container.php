<?php

declare(strict_types=1);

namespace App\Chat;

use App\Chat\Container\BasicContainer;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Werty\Http\Clients\TelegramBot\Client;

/**
 * @property-read Client $client
 * @property-read LoggerInterface $logger
 * @property-read ContextManager $contextManager
 */

class Container extends BasicContainer
{
    protected array $initializers = [];
    public function __construct()
    {
        $this->initializers = [
            'client' => fn() => new Client(config('telegram.bot_token')),
            'logger' => fn() => Log::getLogger(),
            'contextManager' => fn(Container $container) => new ContextManager($container),
        ];
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __get(string $name): mixed
    {
        if ($this->has($name)) {
            return $this->get($name);
        };
        $initializer = $this->initializers[$name] ?? null;
        if ($initializer) {
            $this->items[$name] = $initializer($this);
            return $this->get($name);
        }
        if (class_exists($name)) {
            $this->items[$name] = new $name();
            return $this->get($name);
        }
        throw new Container\Exceptions\NotFoundException("Identifier $name is not defined");
    }
}
