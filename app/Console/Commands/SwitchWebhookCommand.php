<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Requests\SetWebhook;

class SwitchWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:switch-webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Switch proxy to update and vice versa webhook for telegram bot';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $token = config('telegram.bot_token');

        if (empty($token)) {
            $this->error('telegram.bot_token is required in config');

            return 1;
        }

        $this->info('getting webhook');

        $client = new Client($token);

        $webhookInfo = $client->getWebhookInfo();
        $url = $webhookInfo->getUrl();

        if (! $url) {
            $url = env('APP_URL').'/proxy';
        }

        $parts = parse_url($url);

        $paths = [
            '/update' => '/proxy',
            '/proxy' => '/update',
        ];

        $newPath = $paths[$parts['path']];

        $parts['path'] = $newPath;

        $url = $parts['scheme'].'://'.$parts['host'].$parts['path'];

        $res = $this->confirm('url: '.$url.' is this ok?');

        if (! $res) {
            $this->error('aborting');

            return 1;
        }

        $setWebhook = new SetWebhook();
        $setWebhook->setUrl($url);
        $setWebhook->setAllowedUpdates(['message', 'edited_message', 'callback_query', 'chat_member']);

        $result = $client->setWebhook($setWebhook);
        $this->info('response: '.$result ? 'success' : 'fail');

        return 0;
    }
}
