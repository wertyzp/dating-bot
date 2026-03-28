<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\MarkdownV2;
use Werty\Http\Clients\TelegramBot\Requests\SendMessage;
use Werty\Http\Clients\TelegramBot\Types\ParseMode;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    protected function sendToTelegram(Throwable $exception)
    {
        try {
            $token = config('telegram.bot_token');
            $devChatId = env('DEV_CHAT_ID');
            if (empty($token)) {
                Log::warning('Not sending error to telegram: empty telegram.bot_token');

                return;
            }
            if (empty($devChatId)) {
                Log::warning('Not sending error to telegram: empty DEV_CHAT_ID');

                return;
            }
            $client = new Client($token);
            $text = MarkdownV2::escape("$exception");
            if ($exception instanceof NotFoundHttpException) {
                $request = app('request');
                $text = MarkdownV2::escape("404: {$request->getMethod()} {$request->getRequestUri()}");
            }
            $name = env('APP_NAME');
            $url = env('APP_URL');
            $sendMessage = SendMessage::create($devChatId, "[$name]($url)\n```\n$text\n```");
            $sendMessage->setParseMode(ParseMode::MARKDOWN_V2);
            $client->sendMessage($sendMessage);
        } catch (\Throwable $e) {
            Log::error("$e");
        }
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        return parent::render($request, $exception);
    }
}
