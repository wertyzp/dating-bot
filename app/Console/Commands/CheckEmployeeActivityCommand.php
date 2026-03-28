<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Exceptions\HttpException;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Types\MessageEntity;
use Werty\Http\Clients\TelegramBot\Types\User;

class CheckEmployeeActivityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-employee-activity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check employee activity command';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Checking employee activity...');
        // echo curent date
        $this->info(date('Y-m-d H:i:s'));
        $client = new Client(config('telegram.bot_token'));
        /**
         * @var Collection<Employee> $employees
         */
        // select all that work now

        $query = Employee::whereNull('start_warning')->orWhere('start_warning', 0)
            ->where(function ($query) {
                $query->whereRaw('start_time < end_time')
                    ->whereRaw('CURRENT_TIME() BETWEEN start_time AND end_time')
                ->orWhere(function ($query) {
                    $query->whereRaw('start_time > end_time')
                        ->where(function ($q) {
                            $q->whereRaw('CURRENT_TIME() <= end_time')
                                ->orWhereRaw('CURRENT_TIME() >= start_time');
                        });
                });
            });
        /** @var Builder $query */
        $employees = $query->get();
        $this->info("Found " . $employees->count() . " employees that did not send any message yet");
        foreach ($employees as $employee) {
            $start = $employee->start_time;
            $startStr = $start->format('H:i');

            $this->info("Employee did not send any message yet: " . $employee->user->getName());
            Log::info("Employee did not send any message yet: " . $employee->user->getName(), [
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'chat_id' => $employee->chat_id,
                'start_time' => $startStr,
                'end_time' => $employee->end_time->format('H:i'),
                'last_message_at' => $employee->last_message_at,
                'start_warning' => $employee->start_warning,
                'gap_warning' => $employee->gap_warning,
                'now' => (new \DateTime())->format('H:i'),
            ]);
            // send message to employee
            $message = new SendMessage();
            $message->setChatId($employee->chat_id);
            $name = $employee->user->getName();
            $entity = new MessageEntity();
            $entity->setType('mention');
            $entity->setOffset(0);
            $entity->setLength(strlen($name));
            $entity->setUser(new User(['id' => $employee->user_id]));
            $message->setEntities([$entity]);
            $text = "$name, your time is started ($startStr UTC), please send a message to stay active";
            $message->setText($text);
            Log::info("Sending message to employee: {$employee->user->getName()}, chatId: {$employee->chat_id}, text: $text");
            try {
                $client->sendMessage($message);
                $employee->start_warning = true;
                $employee->save();
            } catch (HttpException $e) {
                Log::error("$e");
            }
        }

        $query = Employee::where(function ($query) {
            $query->whereRaw('start_time < end_time')
                ->whereRaw('NOW() BETWEEN ADDTIME(CONCAT(CURDATE(), " ", start_time), "00:35:00") AND CONCAT(CURDATE(), " ", end_time)')
            ->orWhere(function ($query) {
                $query->whereRaw('start_time > end_time') // Overnight shift
                ->where(function ($q) {
                    $q->whereRaw('NOW() >= TIME(ADDTIME(CONCAT(CURDATE(), " ", start_time), "00:35:00"))')
                        ->orWhereRaw('NOW() <= end_time');
                });
            });
        })->where(function ($query) {
                $query->whereRaw('TIMESTAMPDIFF(MINUTE, last_message_at, NOW()) >= 20');
            })
        ->where(function ($q) {
            $q->whereNull('gap_warning')->orWhere('gap_warning', 0);
        });
        /** @var Builder $query */
        $employees = $query->get();
        $this->info("Found " . $employees->count() . " employees that did not send any message for 20 minutes");
        foreach ($employees as $employee) {
            $this->info("Employee did not send any message for 20 minutes: " . $employee->user->getName());
            Log::info("Employee did not send any message for 20 minutes: " . $employee->user->getName(), [
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'chat_id' => $employee->chat_id,
                'start_time' => $employee->start_time->format('H:i'),
                'end_time' => $employee->end_time->format('H:i'),
                'last_message_at' => $employee->last_message_at,
                'start_warning' => $employee->start_warning,
                'gap_warning' => $employee->gap_warning,
                'now' => (new \DateTime())->format('H:i'),
            ]);
            // send message to employee
            $message = new SendMessage();
            $message->setChatId($employee->chat_id);
            $name = $employee->user->getName();
            $entity = new MessageEntity();
            $entity->setType('mention');
            $entity->setOffset(0);
            $entity->setLength(strlen($name));
            $entity->setUser(new User(['id' => $employee->user_id]));
            $message->setEntities([$entity]);
            $text = "$name, your last message was sent 20 minutes ago, please send a message to stay active";
            $message->setText($text);
            Log::info("Sending message to employee: {$employee->user->getName()}, chatId: {$employee->chat_id}, text: $text");
            try {
                $client->sendMessage($message);
                $employee->gap_warning = true;
                $employee->save();
            } catch (HttpException $e) {
                Log::error("$e");
            }
        }

        $query =<<<EOL
UPDATE employees
SET gap_warning = 0,
    start_warning = 0
WHERE (
    start_time < end_time AND CURTIME() > end_time
)
OR (
    start_time > end_time AND CURTIME() > end_time AND CURTIME() < start_time
);
EOL;

        DB::statement($query);
        return;
    }

}
