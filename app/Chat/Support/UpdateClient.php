<?php

declare(strict_types=1);

namespace App\Chat\Support;

use Illuminate\Support\Facades\Log;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Requests\AnswerCallbackQuery;
use Werty\Http\Clients\TelegramBot\Requests\CopyMessage;
use Werty\Http\Clients\TelegramBot\Requests\DeleteMessage;
use Werty\Http\Clients\TelegramBot\Requests\EditMessageCaption;
use Werty\Http\Clients\TelegramBot\Requests\EditMessageMedia;
use Werty\Http\Clients\TelegramBot\Requests\EditMessageReplyMarkup;
use Werty\Http\Clients\TelegramBot\Requests\EditMessageText;
use Werty\Http\Clients\TelegramBot\Requests\ForwardMessage;
use Werty\Http\Clients\TelegramBot\Requests\ReactionType;
use Werty\Http\Clients\TelegramBot\Requests\SendDocument;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Requests\SendPhoto;
use Werty\Http\Clients\TelegramBot\Requests\SetMessageReaction;
use Werty\Http\Clients\TelegramBot\Types\InlineKeyboardMarkup;
use Werty\Http\Clients\TelegramBot\Types\InputFile;
use Werty\Http\Clients\TelegramBot\Types\InputMediaPhoto;
use Werty\Http\Clients\TelegramBot\Types\Message;
use Werty\Http\Clients\TelegramBot\Types\ParseMode;
use Werty\Http\Clients\TelegramBot\Types\ReplyKeyboardMarkup;
use Werty\Http\Clients\TelegramBot\Types\ReplyKeyboardRemove;
use Werty\Http\Clients\TelegramBot\Types\Update;

class UpdateClient
{
    protected int $chatId;
    protected ?int $messageId = null;
    protected ?int $callbackMessageId = null;
    // message id to reply to
    protected ?int $replyMessageId = null;

    // message id to edit
    protected ?int $editMessageId = null;

    protected ?int $originalMessageId = null;

    protected ?AnswerCallbackQuery $answerCallbackQuery = null;
    protected bool $answered = false;

    public const MODE_NEW = 'new';
    public const MODE_EDIT = 'edit';

    protected string $mode = self::MODE_NEW;

    public function __construct(protected Update $update, protected Client $client)
    {
        if ($update->getMessage()) {
            $this->chatId = $update->getMessage()->getChat()->getId();
            $this->messageId = $update->getMessage()->getMessageId();
            $this->replyMessageId = $this->messageId;
            return;
        }

        if ($update->getCallbackQuery()) {
            $this->mode = self::MODE_EDIT;
            $this->chatId = $update->getCallbackQuery()->getMessage()->getChat()->getId();
            $this->callbackMessageId = $update->getCallbackQuery()->getMessage()->getMessageId();
            $this->editMessageId = $this->callbackMessageId;
            $this->messageId = $this->callbackMessageId;
            $this->answerCallbackQuery = AnswerCallbackQuery::create($this->update->getCallbackQuery()->getId());
        }
    }

    public function setModeNew(): void
    {
        $this->mode = self::MODE_NEW;
    }

    public function setModeEdit(): void
    {
        $this->mode = self::MODE_EDIT;
    }

    public function sendMessage(string $message, InlineKeyboardMarkup|ReplyKeyboardMarkup|null $markup = null): Message
    {
        $sendMessage = SendMessage::create($this->chatId, $message);
        if ($markup) {
            $sendMessage->setReplyMarkup($markup);
        }
        return $this->_sendMessage($sendMessage);
    }

    public function sendMessageReply(string $message, InlineKeyboardMarkup|ReplyKeyboardMarkup|null $markup = null): Message
    {
        $sendMessage = SendMessage::create($this->chatId, $message);
        $sendMessage->setReplyToMessageId($this->replyMessageId ?? $this->editMessageId);
        if ($markup) {
            $sendMessage->setReplyMarkup($markup);
        }
        return $this->_sendMessage($sendMessage);
    }

    public function editMessageText(string $text, InlineKeyboardMarkup|ReplyKeyboardMarkup|null $markup = null): Message
    {
        $sendMessage = EditMessageText::create($this->chatId, $this->editMessageId ?? $this->replyMessageId, $text);
        if ($markup) {
            $sendMessage->setReplyMarkup($markup);
        }
        return $this->_editMessage($sendMessage);
    }

    public function sendMarkdownMessageReply(string $message, InlineKeyboardMarkup|ReplyKeyboardMarkup|null $markup = null): Message
    {
        $sendMessage = SendMessage::create($this->chatId, $message);
        $sendMessage->setParseMode(ParseMode::MARKDOWN_V2);
        if ($markup) {
            $sendMessage->setReplyMarkup($markup);
        }
        $sendMessage->setReplyToMessageId($this->replyMessageId ?? $this->editMessageId);
        return $this->_sendMessage($sendMessage);
    }

    public function deleteMessage(?int $messageId = null): void
    {
        $messageId ??= $this->messageId;
        $deleteMessage = DeleteMessage::create($this->chatId, $messageId);
        $this->client->deleteMessage($deleteMessage);
    }

    public function deleteMessageFromChat(int|string $chatId, int $messageId): void
    {
        $deleteMessage = DeleteMessage::create($chatId, $messageId);
        $this->client->deleteMessage($deleteMessage);
    }

    private function _sendMessage(SendMessage $sendMessage): Message
    {
        return $this->client->sendMessage($sendMessage);
    }

    private function _editMessage(EditMessageText $editMessageText): Message
    {
        return $this->client->editMessageText($editMessageText);
    }

    public function upsertMessage(string $text, InlineKeyboardMarkup|ReplyKeyboardMarkup|null $keyboard = null): Message
    {
        if ($this->isModeEdit()) {
            return $this->editMessageText($text, $keyboard);
        } else {
            return $this->sendMessage($text, $keyboard);
        }
    }

    public function isModeEdit(): bool
    {
        return $this->mode === self::MODE_EDIT;
    }

    public function isCallback(): bool
    {
        return $this->update->getCallbackQuery() !== null;
    }

    public function upsertPhoto(string $caption, string|InputFile $photo, InlineKeyboardMarkup|ReplyKeyboardMarkup|null $keyboard = null): Message
    {
        if ($this->isModeEdit()) {
            return $this->editPhoto($caption, $photo, $keyboard);
        } else {
            return $this->sendPhoto($caption, $photo, $keyboard);
        }
    }

    public function editPhoto(string $caption, string|InputFile $photo, InlineKeyboardMarkup|ReplyKeyboardMarkup|null $keyboard = null): Message
    {
        if ($photo instanceof InputFile) {
            $photo = $photo->getFilename();
        }
        $photoMedia = InputMediaPhoto::create($photo);
        $photoMedia->setCaption($caption);
        $editMessageMedia = EditMessageMedia::create($this->chatId, $this->editMessageId ?? $this->replyMessageId, $photoMedia);
        if ($keyboard) {
            $editMessageMedia->setReplyMarkup($keyboard);
        }
        return $this->client->editMessageMedia($editMessageMedia);

    }
    public function sendPhoto(string $caption, string|InputFile $photo, InlineKeyboardMarkup|ReplyKeyboardMarkup|null $keyboard = null): Message
    {
        $sendPhoto = SendPhoto::create($this->chatId, $photo);
        $sendPhoto->setCaption($caption);
        if ($keyboard) {
            $sendPhoto->setReplyMarkup($keyboard);
        }
        return $this->client->sendPhoto($sendPhoto);
    }

    public function answerCallbackQuery(?string $text = null, bool $showAlert = false): void
    {
        if ($this->answerCallbackQuery) {
            if ($this->answered) {
                throw new \RuntimeException('Callback query already answered');
            }
            if ($text) {
                $this->answerCallbackQuery->setText($text);
            }
            if ($showAlert) {
                $this->answerCallbackQuery->setShowAlert(true);
            }
            $this->answered = $this->client->answerCallbackQuery($this->answerCallbackQuery);
        }
    }

    public function setReplyMessageId(?int $replyMessageId): void
    {
        $this->replyMessageId = $replyMessageId;
    }

    public function setEditMessageId(?int $editMessageId): void
    {
        $this->editMessageId = $editMessageId;
    }

    public function reactThumbsUp(?int $messageId = null): void
    {
        $this->react(ReactionType::THUMBS_UP, $messageId);
    }

    public function reactThumbsDown(?int $messageId = null): void
    {
        $this->react(ReactionType::THUMBS_DOWN, $messageId);
    }

    public function react(string $type, ?int $messageId = null): void
    {
        $messageId ??= $this->replyMessageId ?? $this->editMessageId;
        $reactionType = ReactionType::create($type);
        $this->client->setMessageReaction(SetMessageReaction::create($this->chatId, $messageId, [$reactionType]));
    }

    public function forwardMessage(int $toChatId): Message
    {
        $forwardMessage = ForwardMessage::create($toChatId, $this->chatId, $this->messageId);
        return $this->client->forwardMessage($forwardMessage);
    }

    public function copyMessage(int $toChatId): Message
    {
        $copyMessage = CopyMessage::create($toChatId, $this->chatId, $this->messageId);
        return $this->client->copyMessage($copyMessage);
    }

    public function copyAnyMessage(int $sourceChatId, int $sourceMessageId, int $toChatId): Message
    {
        $copyMessage = CopyMessage::create($toChatId, $sourceChatId, $sourceMessageId);
        return $this->client->copyMessage($copyMessage);
    }

    public function getUpdate(): Update
    {
        return $this->update;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function removeKeyboard(?int $messageId = null): void
    {
        $messageId ??= $this->replyMessageId ?? $this->editMessageId;
        $keyboard = new InlineKeyboardMarkup([
            'inline_keyboard' => []
        ]);
        $this->client->editMessageReplyMarkup(EditMessageReplyMarkup::create($this->chatId, $messageId, $keyboard));
    }

    public function sendDocument(string $caption, string|InputFile $document, InlineKeyboardMarkup|ReplyKeyboardMarkup|null $keyboard = null): Message
    {
        $sendDocument = SendDocument::create($this->chatId, $document);
        $sendDocument->setCaption($caption);
        if ($keyboard) {
            $sendDocument->setReplyMarkup($keyboard);
        }
        return $this->client->sendDocument($sendDocument);
    }

    public function __destruct()
    {
        if (!$this->answered) {
            $this->answerCallbackQuery();
        }
    }

    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    public function getCallbackMessageId(): ?int
    {
        return $this->callbackMessageId;
    }

    public function setTimeout(int $seconds, callable $callable): void
    {
        sleep($seconds);
        $callable($this);
    }

    public function inSecond(callable $callable): void
    {
        $this->setTimeout(1, $callable);
    }

}
