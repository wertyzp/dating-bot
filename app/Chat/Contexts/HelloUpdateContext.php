<?php

declare(strict_types=1);

namespace App\Chat\Contexts;

use App\Chat\ContextManager;
use App\Chat\Support\UpdateClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Types\MessageEntity;
use Werty\Http\Clients\TelegramBot\Types\User;

function utf16_strlen($text) {
    return strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')) / 2;
}

function utf16_offset($text, $start) {
    return utf16_strlen(mb_substr($text, 0, $start));
}

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
        $update = $this->uc->getUpdate();

        $chatMember = $update->getChatMember()?->getNewChatMember();
        if (!$chatMember) {
            $this->logger->warning('No chat member info in leave event');
            return;
        }
        $joinedUser = $chatMember->getUser();
        $name = $this->getName($joinedUser);
        $usernameTag = $joinedUser->getUsername() ? "@{$joinedUser->getUsername()}" : '';
        $text =<<<EOL
{$name} $usernameTag,
Вітаємо у чаті «ВІН — ВОНА | ЖИВЕ СПІЛКУВАННЯ» 👋

Це простір дорослого, приємного спілкування між чоловіками та жінками.

📌 При вході, будь ласка:
• представтесь (імʼя + кілька слів про себе)
• додайте фото

🔹 Повага, адекватність і жива атмосфера — наші головні правила.

Приємного спілкування ☕️✨
EOL;
        $chatId = $this->uc->getChatId();
        $message = new SendMessage();
        $message->setChatId($chatId);
        $message->setText($text);
        $message->setChatId($this->uc->getChatId());
        $entity = new MessageEntity();
        $entity->setType('text_mention');
        $entity->setOffset(0);
        $entity->setLength(utf16_strlen($name));
        $entity->setUser(new User(['id' => $joinedUser->getId()]));

        $message->setEntities([$entity]);
        $client = $this->uc->getClient();
        $client->sendMessage($message);

        $this->logger->info('Greeted joined user', ['chat_id' => $data['chat']['id'] ?? null, 'name' => $name]);
    }

    public function sayBye(): void
    {
        $update = $this->uc->getUpdate();

        $chatMember = $update->getChatMember()?->getNewChatMember();
        if (!$chatMember) {
            $this->logger->warning('No chat member info in leave event');
            return;
        }
        $leftUser = $chatMember->getUser();
        $message = new SendMessage();
        $prefix = 'Бувай,';
        $suffix = 'Сподіваємось побачити тебе знову! 👋';
        $name = $this->getName($leftUser);
        $entity = new MessageEntity();
        $entity->setType('text_mention');
        $entity->setOffset(utf16_strlen($prefix)+1);
        $entity->setLength(utf16_strlen($name));
        $entity->setUser(new User(['id' => $leftUser->getId()]));
        $usernameTag = $leftUser->getUsername() ? "@{$leftUser->getUsername()}" : '';
        $message->setEntities([$entity]);
        $message->setText("$prefix $name $usernameTag! $suffix");
        $message->setChatId($this->uc->getChatId());
        $this->logger->info('Greeted left user', $message->toArray());
        $client = $this->uc->getClient();
        $client->sendMessage($message);
        $this->logger->info('Sent leave message');
    }

    protected function getName(User $user): string
    {
        // format: first name + username (if available) + last name (if available)
        $name = $user->getFirstName();
        if ($user->getLastName()) {
            $name .= " {$user->getLastName()}";
        }
        return $name;
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
