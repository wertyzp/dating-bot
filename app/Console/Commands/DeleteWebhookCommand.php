<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Requests\SetWebhook;

class DeleteWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:delete-webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete webhook';

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

        $this->info('deleting webhook');
        $client = new Client($token);
        $result = $client->deleteWebhook(false);
        $this->info('response: '.$result ? 'success' : 'fail');

        return 0;
    }
}
