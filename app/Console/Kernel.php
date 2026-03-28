<?php

namespace App\Console;

use App\Console\Commands\CheckDailyActionsCommand;
use App\Console\Commands\CheckEmployeeActivityCommand;
use App\Console\Commands\DeleteWebhookCommand;
use App\Console\Commands\ExtractLinksCommand;
use App\Console\Commands\GetChatCommand;
use App\Console\Commands\GetCommandsCommand;
use App\Console\Commands\GetMeCommand;
use App\Console\Commands\GetWebhookInfoCommand;
use App\Console\Commands\SetAllWebhooksCommand;
use App\Console\Commands\SetMyCommandsCommand;
use App\Console\Commands\SetWebhookCommand;
use App\Console\Commands\SwitchWebhookCommand;
use App\Console\Commands\TestCommand;
use App\Console\Commands\UpdateCommand;
use App\Console\Commands\UpdateLinksCommand;
use App\Console\Commands\UploadReportsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        UpdateCommand::class,
        GetWebhookInfoCommand::class,
        SetWebhookCommand::class,
        SetAllWebhooksCommand::class,
        SwitchWebhookCommand::class,
        GetCommandsCommand::class,
        SetMyCommandsCommand::class,
        GetChatCommand::class,
        GetMeCommand::class,
        CheckEmployeeActivityCommand::class,
        TestCommand::class,
        ExtractLinksCommand::class,
        CheckDailyActionsCommand::class,
        UpdateLinksCommand::class,
        UploadReportsCommand::class,
        DeleteWebhookCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('app:check-employee-activity')->everyFiveMinutes();
        $schedule->command('app:check-daily-actions')->hourly();
        $schedule->command('app:upload-reports')->hourly();
    }
}
