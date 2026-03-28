<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Chat\Services\LinkService;
use App\Models\ForwardedMessage;
use App\Models\Link;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Requests\GetChat;

class ExtractLinksCommand extends Command
{
    /**
     * The name and signature of the console command.
     * optional
     * @var string
     */
    protected $signature = 'app:extract-links';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract links from all messages and put to links table';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /** @var LinkService $linkService */
        $linkService = app(LinkService::class);
        // in portion select 1000 messages
        $count = 0;
        Message::query()
            ->chunk(1000, function ($messages) use ($linkService, &$count) {
                /** @var Collection<Message> $messages */
                foreach ($messages as $message) {
                    $linksMap = $linkService->getLinksFromText($message->text);
                    foreach ($linksMap as $type => $links) {
                        foreach ($links as $link) {
                            // check if this message was forwarded and not deleted
                            $exists = ForwardedMessage::query()
                                ->where('source_message_id', $message->message_id)
                                ->where('source_chat_id', $message->chat_id)
                                ->exists();
                            if (!$exists) {
                                $this->warn("Message {$message->id} is not present: is in comment or deleted");
                                continue;
                            }
                            // check for dup
                            /** @var Link $existingLink */
                            $existingLink = Link::query()
                                ->where('link', $link)
                                ->where('chat_id', $message->chat_id)
                                ->first();
                            if ($existingLink) {
                                $existingLink->message_id = $message->message_id;
                                $existingLink->created_at = $message->created_at;
                                $this->info("Link {$link} updated to message {$message->message_id}");
                                continue;
                            }
                            $isDaily = false;
                            // check if text has 'reddit daily' , 'daily reddit', 'quora daily', 'daily quora'
                            if (preg_match('/(reddit|quora) daily/i', $message->text)) {
                                $isDaily = true;
                            }
                            if (preg_match('/daily (reddit|quora)/i', $message->text)) {
                                $isDaily = true;
                            }
                            $linkModel = new Link();
                            $linkModel->link = $link;
                            $linkModel->type = $type;
                            $linkModel->chat_id = $message->chat_id;
                            $linkModel->message_id = $message->message_id;
                            $linkModel->user_id = $message->user_id;
                            $linkModel->is_daily = $isDaily;
                            $linkModel->created_at = $message->created_at;
                            $linkModel->updated_at = $message->updated_at;
                            $linkModel->save();
                            $count++;
                            $this->info("Saved link: {$link} of type {$type} from message {$message->id}");
                        }
                    }
                }
            });
        $this->info("Total links extracted: {$count}");
        return 0;
    }
}
