<?php

declare(strict_types=1);

namespace App\Chat\Contexts;

use App\Chat\ContextManager;
use App\Chat\Support\UpdateClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class HelloUpdateContext extends BaseContext implements LoggerAwareInterface
{
    public function __construct(
        protected UpdateClient $uc,
        protected ContextManager $contextManager,
        protected LoggerInterface $logger,
    ) {
    }

    public function greetNewUser(): void
    {
        $message = $this->uc->getUpdate()->getMessage();
        if (!$message) {
            return;
        }

        $data = $message->toArray();
        $text = mb_strtolower((string)($data['text'] ?? ''));

        $isJoinEvent = !empty($data['new_chat_member'])
            || !empty($data['new_chat_members'])
            || str_contains($text, 'new used joined')
            || str_contains($text, 'new user joined');

        if (!$isJoinEvent) {
            return;
        }

        $name = $this->resolveJoinedUserName($data);
        $this->uc->sendMessage("Hello, {$name}!");
        $this->logger->info('Greeted joined user', ['chat_id' => $data['chat']['id'] ?? null, 'name' => $name]);
    }

    public function sayBye(): void
    {
        $message = $this->uc->getUpdate()->getMessage();
        if (!$message) {
            return;
        }

        $data = $message->toArray();
        if (empty($data['left_chat_member'])) {
            return;
        }

        $this->uc->sendMessage('bye');
        $this->logger->info('Sent leave message', ['chat_id' => $data['chat']['id'] ?? null]);
    }

    protected function resolveJoinedUserName(array $messageData): string
    {
        $member = null;

        if (!empty($messageData['new_chat_members']) && is_array($messageData['new_chat_members'])) {
            $member = $messageData['new_chat_members'][0] ?? null;
        } elseif (!empty($messageData['new_chat_member']) && is_array($messageData['new_chat_member'])) {
            $member = $messageData['new_chat_member'];
        }

        if (is_array($member)) {
            if (!empty($member['first_name'])) {
                return (string)$member['first_name'];
            }
            if (!empty($member['username'])) {
                return '@' . $member['username'];
            }
        }

        return 'there';
    }

    public function setup(array $params = []): void
    {
    }

    public function teardown(): void
    {
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
