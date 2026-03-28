<?php

declare(strict_types=1);

namespace App\Chat;

use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\MarkdownV2;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Types\ParseMode;

class Debug
{
    public static function message(mixed $format, ...$args): void
    {
        if (!env('APP_DEBUG')) {
            return;
        }
        $sendMessage = new SendMessage();
        $sendMessage->setChatId(env('DEV_CHAT_ID'));
        if (is_array($format) || is_object($format)) {
            $message = json_encode($format, JSON_PRETTY_PRINT);
            $sendMessage->setText("```json\n".MarkdownV2::escape($message)."\n```");
            $sendMessage->setParseMode(ParseMode::MARKDOWN_V2);
        } else {
            $message = sprintf($format, ...$args);
            $sendMessage->setText($message);
        }
        $client = new Client(config('telegram.bot_token'));
        $client->sendMessage($sendMessage);
    }
}
