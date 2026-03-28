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

class UpdateLinksCommand extends Command
{
    /**
     * The name and signature of the console command.
     * optional
     * @var string
     */
    protected $signature = 'app:update-links';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update links from links in table';

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
        Link::query()
            ->chunk(1000, function ($links) use ($linkService, &$count) {
                /** @var Collection<Link> $links */
                foreach ($links as $link) {
                    $newLinkType = $linkService->getLinkType($link->link);
                    if ($newLinkType !== $link->type) {
                        $link->type = $newLinkType;
                        $link->save();
                        $this->info("Link {$link->link} updated to type {$newLinkType}");
                        $count++;
                    }
                }
            });
        $this->info("Total links fixed: {$count}");
        return 0;
    }
}
