<?php

declare(strict_types=1);

namespace App\Chat\ContextHandlers;

use App\Chat\Condition\Expression;
use App\Chat\Contexts\HelloUpdateContext;
use App\Chat\Contexts\UpdateContext;
use Werty\Http\Clients\TelegramBot\Types\MessageEntity;

class UpdateContextHandler extends BasicContextHandler
{
    public function __construct()
    {
        // Join events can come from Telegram service fields or custom text markers.
        //$this->when(Expression::exists('message.new_chat_members'), [HelloUpdateContext::class, 'greetNewUser']);
        //$this->when(Expression::exists('message.new_chat_member'), [HelloUpdateContext::class, 'greetNewUser']);
        //$this->when(Expression::text('message.text', 'new used joined'), [HelloUpdateContext::class, 'greetNewUser']);
        //$this->when(Expression::text('message.text', 'new user joined'), [HelloUpdateContext::class, 'greetNewUser']);

        $this->when(Expression::text('chat_member.new_chat_member.status', 'member'), [HelloUpdateContext::class, 'greetNewUser']);
        $this->when(Expression::text('chat_member.new_chat_member.status', 'left'), [HelloUpdateContext::class, 'sayBye']);
        //$this->when(Expression::exists('message.left_chat_member'), [HelloUpdateContext::class, 'sayBye']);

        // restart session-independent command
        $this->when(
            Expression::startsWith('message.text', '/start'),
            [UpdateContext::class, 'reset']
        );
        $botCommand = MessageEntity::TYPE_BOT_COMMAND;
        $this->when(Expression::has('message.entities', "type:$botCommand"), [UpdateContext::class, 'command']);
    }

}
