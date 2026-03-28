<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Requests\SetWebhook;

class SetAllWebhooksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-all-webhooks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set All Webhooks for all supported telegram bots';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $urlFormat = env('APP_URL')."/proxy?bot_key=%s";

        $tokens = config('telegram.bot_tokens');

        foreach ($tokens as $id => $token) {
            $url = sprintf($urlFormat, $id);
            $this->info('url: '.$url);
            $this->info('setting webhook');
            $setWebhook = new SetWebhook();
            $setWebhook->setUrl($url);
            $client = new Client($token);
            $result = $client->setWebhook($setWebhook);
            $this->info("bot id: $id response: ".($result ? 'success' : 'fail'));
        }
        return 0;
    }
}
