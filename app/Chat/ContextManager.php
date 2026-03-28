<?php

declare(strict_types=1);

namespace App\Chat;

use App\Chat\Contexts\HelloUpdateContext;
use App\Chat\Contexts\UpdateContext;
use App\Chat\Support\UpdateClient;
use Werty\Http\Clients\TelegramBot\Types\Update;

class ContextManager
{
    protected ?UpdateClient $updateClient = null;
    public function __construct(protected Container $container)
    {
    }

    public function getUpdateClient(Update $update): UpdateClient
    {
        if (!isset($this->updateClient)) {
            $this->updateClient = new UpdateClient($update, $this->container->client);
        }
        return $this->updateClient;
    }

    public function getContext(string $class, Update $update): Contexts\Context
    {
        $container = $this->container;
        return match ($class) {
            HelloUpdateContext::class => new HelloUpdateContext(
                $this->getUpdateClient($update),
                $this,
                $container->logger,
            ),
            UpdateContext::class => new UpdateContext(
                $this->getUpdateClient($update),
                $this,
                $container->logger,
            ),
        };
    }

    public function reportContextException(Contexts\Exceptions\Exception $e): void
    {
        $text = $e->getUserFriendlyMessage();
        if ($this->updateClient->isCallback()) {
            $this->updateClient->answerCallbackQuery($text, true);
        } else {
            $this->updateClient->sendMessage($text);
        }
        $this->container->logger->error("$e");
    }
}
