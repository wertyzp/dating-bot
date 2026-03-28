<?php

declare(strict_types=1);

namespace App\Services;

use App\Chat\Support\UpdateClient;
use App\Models\Link;
use App\Models\DailyMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Werty\Http\Clients\TelegramBot\Exceptions\HttpException;
use Werty\Http\Clients\TelegramBot\Requests\EditMessageText;
use Werty\Http\Clients\TelegramBot\Requests\PinChatMessage;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Response;

class LinkService
{
    public function __construct(
        private readonly UpdateClient $uc,
        private readonly LoggerInterface $logger,
    ) {
    }
    /**
     * @throws \Exception
     */
    protected function isUrlAccessible($url): bool
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,  // follow redirects
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,          // we only need headers
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Bot/1.0)', // pretend to be browser
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $error = curl_error($ch);
        if ($error) {
            throw new \Exception("Curl error: $error");
        }
        // good 2xx codes
        $goodCodes = [200];
        if (in_array($httpCode, $goodCodes)) {
            return true;
        }

        return false;
    }
    protected function removeInaccessibleLinks(Collection $links): void
    {
        $removed = [];
        $now = new \DateTime();
        foreach ($links as $key => $link) {
            try {
                $verifiedAt = $link->verified_at;
                // if verified_at > 2h
                if ($verifiedAt && $verifiedAt->diffInHours($now) < 2) {
                    $this->logger->info('Link is already verified', ['link' => $link->link]);
                    continue;
                }
                if (!$this->isUrlAccessible($link->link)) {
                    $link->delete();
                    // remove from array
                    $removed[] = $key;
                } else {
                    $link->verified_at = $now->format('Y-m-d H:i:s');
                    $link->save();
                    $this->logger->info("Link is accessible: $link->verified_at", ['link' => $link->link]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Error checking link', ['exception' => $e]);
                // do not do any action if request failed
            }
        }
        foreach ($removed as $key) {
            $links->forget($key);
        }
    }
    /**
     * @param Collection<Link> $links
     * @param string $type
     * @return string
     */

    protected function createLinksMessageText(Collection $links, string $type): string
    {
        $n = $links->count();
        $typeStr = ucfirst($type);
        $messagePrefix = "$typeStr Dailies ($n):\n";
        $messageText = '';
        foreach ($links as $link) {
            $messageText .= $link->link . "\n\n";
        }
        return "$messagePrefix$messageText";
    }
    /**
     * @param string|int $chatId
     * @param string $type
     * @return DailyMessage|null
     */
    public function updateDailyLinksMessage(string|int $chatId, string $type): ?DailyMessage
    {
        try {

            // select all daily links by employees in current chat
            /** @var Collection<Link> $links */
            $links = Link::where('chat_id', $chatId)
                ->orderBy('created_at', 'asc')
                ->where('type', $type)
                ->get();
            $links = $links->unique('link');

            DB::beginTransaction();
            if ($type === 'reddit') {
                $this->removeInaccessibleLinks($links);
            }
            if ($links->isEmpty()) {
                DB::commit();
                $this->logger->info("No $type links found today");
                return null;
            }

            $maxMessageSize = 4096;
            $messageText = $this->createLinksMessageText($links, $type);
            while (mb_strlen($messageText) > $maxMessageSize) {
                // extract first link, delete it
                /** @var Link $firstLink */
                $firstLink = $links->first();
                $firstLink->delete();
                $links->shift();
                $messageText = $this->createLinksMessageText($links, $type);
            }

            // get last daily message for this chat
            /** @var DailyMessage $dailyMessage */
            $dailyMessage = DailyMessage::where('chat_id', $chatId)->where('type', $type)->orderBy('created_at', 'desc')->first();

            // check if message today by current date
            if ($dailyMessage) {
                if ($dailyMessage->text == $messageText) {
                    $this->logger->info('Message is the same, skipping', ['message' => $dailyMessage->text]);
                    DB::commit();
                    return null;
                }
                // update message
                $dailyMessage->text = $messageText;
                $dailyMessage->save();
                $editMessage = new EditMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $dailyMessage->message_id,
                    'text' => $messageText,
                    'link_preview_options' => json_encode(['is_disabled' => true]),
                ]);
                try {
                    $this->uc->getClient()->editMessageText($editMessage);
                } catch (HttpException $e) {
                    $sendMessage = new SendMessage([
                        'chat_id' => $chatId,
                        'text' => $messageText,
                        'link_preview_options' => ['is_disabled' => true],
                    ]);
                    $newMessage = $this->uc->getClient()->sendMessage($sendMessage);
                    $dailyMessage->message_id = $newMessage->getMessageId();
                    $dailyMessage->save();

                    $pinChatMessage = PinChatMessage::create($chatId, $newMessage->getMessageId());
                    $this->uc->getClient()->pinChatMessage($pinChatMessage);
                    $response = $e->getResponse();
                    if ($response instanceof Response) {
                        $responseText = json_encode($response->toArray());
                    } else {
                        $responseText = $response;
                    }
                    $this->logger->error('Error editing message', ['response' => $responseText]);
                } catch (\Throwable $e) {
                    $this->logger->error('Error editing message', ['exception' => $e]);
                }
                DB::commit();
                return $dailyMessage;
            }
            $this->logger->info('Creating new message', ['message' => $messageText]);
            // message doesn't exist
            $dailyMessage = DailyMessage::create([
                'chat_id' => $chatId,
                'text' => $messageText,
                'type' => $type,
                'message_id' => 0,
            ]);

            $sendMessage = new SendMessage([
                'chat_id' => $chatId,
                'text' => $messageText,
                'link_preview_options' => ['is_disabled' => true],
            ]);

            $newMessage = $this->uc->getClient()->sendMessage($sendMessage);
            $dailyMessage->message_id = $newMessage->getMessageId();
            $dailyMessage->save();

            $pinChatMessage = PinChatMessage::create($chatId, $newMessage->getMessageId());
            $this->uc->getClient()->pinChatMessage($pinChatMessage);

            DB::commit();
            return $dailyMessage;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->logger->error('Error checking today messages', ['exception' => $e]);
            return null;
        }
    }
}
