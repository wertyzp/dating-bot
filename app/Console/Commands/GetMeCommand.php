<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Werty\Http\Clients\TelegramBot\Client;

class GetMeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:get-me';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get my info';

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

        $client = new Client($token);
        $result = $client->getMe();
        $this->info(var_export($result, true));

        return 0;
    }
}
