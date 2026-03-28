<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Requests\SetWebhook;

class SetWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook {--url= : url}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Webhook for telegram bot';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $url = $this->option('url');
        if (! $url) {
            $this->error('url is required');

            return 1;
        }

        $token = config('telegram.bot_token');

        if (empty($token)) {
            $this->error('telegram.bot_token is required in config');

            return 1;
        }

        $this->info('url: '.$url);
        $this->info('setting webhook');
        $setWebhook = new SetWebhook();
        $setWebhook->setUrl($url);
        $client = new Client($token);
        $result = $client->setWebhook($setWebhook);
        $this->info('response: '.$result ? 'success' : 'fail');

        return 0;
    }
}
