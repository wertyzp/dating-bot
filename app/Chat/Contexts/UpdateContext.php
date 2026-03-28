<?php

declare(strict_types=1);

namespace App\Chat\Contexts;

use App\Chat\ContextManager;
use App\Chat\Contexts\Forms\EmployeesReportDialog;
use App\Chat\Contexts\Forms\Form;
use App\Chat\Contexts\Forms\LinksReportDialog;
use App\Chat\Contracts\ContextHandler;
use App\Chat\Debug;
use App\Chat\Helpers\Types\InlineKeyboardMarkup;
use App\Chat\Services\RedditService;
use App\Chat\Services\LinkService;
use App\Chat\Support\UpdateClient;
use App\Models\Chat;
use App\Models\ChatForwarding;
use App\Models\ChatRatio;
use App\Models\ChatSetup;
use App\Models\ChatUser;
use App\Models\EmployeeRatio;
use App\Models\Link;
use App\Models\DailyMessage;
use App\Models\Employee;
use App\Models\ForwardedMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Werty\Http\Clients\TelegramBot\Exceptions\HttpException;
use Werty\Http\Clients\TelegramBot\MarkdownV2;
use Werty\Http\Clients\TelegramBot\Requests\EditMessageCaption;
use Werty\Http\Clients\TelegramBot\Requests\EditMessageText;
use Werty\Http\Clients\TelegramBot\Requests\PinChatMessage;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Response;
use Werty\Http\Clients\TelegramBot\Types\Message;

class UpdateContext extends BaseContext implements LoggerAwareInterface
{
    public function __construct(protected UpdateClient $uc,
                                protected ContextManager    $contextManager,
                                protected LoggerInterface $logger,
    )
    {
    }

    protected function checkAccess(): bool
    {
        $adminChatId = (int)env('ADMIN_CHAT_ID');
        $devChatId = (int)env('DEV_CHAT_ID');
        $message = $this->uc->getUpdate()->getMessage();
        $cb = $this->uc->getUpdate()->getCallbackQuery();
        $fromId = $message ? $message->getFrom()->getId() : $cb->getFrom()->getId();
        return $fromId === $adminChatId || $fromId === $devChatId;
    }


    protected function migrateChat($oldChatId, $chatId): void
    {
        DB::beginTransaction();
        if (!ChatUser::where('chat_id', $chatId)->exists()) {
            ChatUser::where('chat_id', $oldChatId)->update(['chat_id' => $chatId]);
        }
        Chat::where('id', $oldChatId)->delete();
        DB::commit();
    }

    public function update(): void
    {
        $cb = $this->uc->getUpdate()->getCallbackQuery();
        if ($cb) {
            if (!$this->checkAccess()) {
                $this->uc->answerCallbackQuery('Access denied', true);
                return;
            }
            $data = $cb->getData();
            [$callback, $params] = DataQuery::decode($data);
            call_user_func($callback, ...array_values($params));
            return;
        }

        $message = $this->uc->getUpdate()->getMessage();
        $isEdited = false;
        if (!$message) {
            $editedMessage = $this->uc->getUpdate()->getEditedMessage();
            if ($editedMessage) {
                $isEdited = true;
                $this->logger->info('Edited message', ['message' => $editedMessage->toArray()]);
                $message = $editedMessage;
            } else {
                $this->logger->info('No message or edited message');
                return;
            }
        }
        $chat = $message->getChat();
        $chatId = $chat->getId();
        $user = $message->getFrom();


        Chat::updateOrCreate(['id' => $chatId], $chat->toArray());
        if ($message->getMigrateFromChatId() && $user->isIsBot()) {
            $oldChatId = $message->getMigrateFromChatId();
            $this->logger->info('Migrating chat to new id', ['chat' => $message->getChat()->toArray()]);
            $this->migrateChat($oldChatId, $chatId);
        }
        User::updateOrCreate(['id' => $user->getId()], $user->toArray());
        ChatUser::updateOrCreate(['chat_id' => $chatId, 'user_id' => $user->getId()]);

        if ($user->isIsBot()) {
            return;
        }

        $messageText = $message->getText() ?? $message->getCaption() ?? '';
        \App\Models\Message::create([
            'chat_id' => $chatId,
            'user_id' => $user->getId(),
            'message_id' => $message->getMessageId(),
            'text' => $messageText,
            'data' => $message->toArray(),
        ]);
    }

    public function restart(): void
    {
        Debug::message('Restarting');
    }

    public function reset(): void
    {

    }

    public function setup(array $params = []): void
    {

    }

    public function teardown(): void
    {

    }

    public function updateForm(string $formId, string $keyCode): void
    {
        if (!$this->uc->getUpdate()->getCallbackQuery()) {
            $this->logger->error('No callback query');
            return;
        }

        $key = "form-$formId";
        $cache = Cache::get($key);
        if (!$cache) {
            $this->logger->error('No cache found for form', ['$formId' => $formId]);
            return;
        }
        /** @var Form $form */
        $form = unserialize($cache);
        if (!$form) {
            $this->logger->error('No form found', ['$formId' => $formId]);
            return;
        }
        $form->handleKey($keyCode);
        $form->update();
        Cache::set($key, serialize($form), 3600);
        $updated = $form->render($this->uc);
        if (!$updated) {
            $this->uc->deleteMessage($this->uc->getCallbackMessageId());
            Cache::forget($key);
        }
    }

    public function command(): ?ContextHandler
    {
        if (!$this->checkAccess()) {
            return null;
        }

        $message = $this->uc->getUpdate()->getMessage();
        $text = $message->getText();
        $cmdPrefixes = ['/'];

        $prefix = null;
        foreach ($cmdPrefixes as $cmdPrefix) {
            if (str_starts_with($text, $cmdPrefix)) {
                $prefix = $cmdPrefix;
                break;
            }
        }

        if (!$prefix) {
            // а как мы вообще сюда попали?
            return null;
        }

        $localArgv = substr($text, strlen($prefix));
        $commandParts = explode(' ', $localArgv);
        [$command, $arguments] = $commandParts + [null, null];
        $commandParts = explode('@', $command);
        $command = $commandParts[0];
        if ($message->getChat()->getType() !== 'private') {
            if (count($commandParts) < 2) {
                $this->logger->info('No bot name in command, disabled for group chats');
                return null;
            }
            $commandBotName = $commandParts[1];
            $botUsername = Cache::get('bot-username');

            if (empty($botUsername)) {
                $this->logger->error('Bot username not found');
                return null;
            }

            if ($botUsername !== $commandBotName) {
                $this->logger->info('This command is not for us');
                return null;
            }
        }
        $handler = match ($command) {
            'help' => [self::class, 'help'],
            default => null,
        };


        if (!$handler) {
            return null;
        }

        $method = end($handler);

        if (!method_exists($this, $method)) {
            Debug::message('Method not found: '.$method);
            return null;
        }

        $this->$method($arguments);
        return null;
    }

    public function help(?string $topic = null): void
    {
        $text = new MarkdownV2();
        $topic = $topic ?? 'default';
        switch ($topic) {
            default:
                $text->line('Help is not present');
        }
        $this->uc->sendMarkdownMessageReply($text->toString());
    }
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
