<?php

namespace App\Http\Controllers;

use App\Chat\Handler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpClient\HttpClient;
use Werty\Http\Clients\TelegramBot\Types\Update;

class CallbackController extends Controller
{
    public function update(Request $request)
    {
        $botKey = $request->query('bot_key', 0);
        Log::info($request->getContent());
        $this->queue($request->getContent(), $botKey);
        return new Response('ok');
    }
    protected function directHandle(Request $request)
    {
        /** @var Handler $handler */
        $handler = app(Handler::class);
        $update = new Update(json_decode($request->getContent()));
        try {
            $handler->handle($update);
        } catch (\Throwable $e) {
            Log::error("$e");
        }
    }

    public function queue(string $input, string $botKey)
    {
        $dspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $botKey = (int)$botKey;

        $php = getenv('PHP_BINARY');

        $command = "$php bin/update.php --bot-key=$botKey";
        $ph = proc_open($command, $dspec, $pipes);

        if (! is_resource($ph)) {
            Log::error('Unable to open update processor');
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]); // this will release target process fread
        $data = fread($pipes[1], 4096); // this will block until target process fwrite
        if (! empty($data)) {
            Log::info($data);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);

        $result = proc_close($ph);
        if ($result != 0) {
            Log::info("bin/update.php returned $result");
        }
    }

    public function proxy(Request $request)
    {
        $botKey = $request->query('bot_key');
        $input = $request->getContent();
        // forward http request
        $client = HttpClient::create();

        $proxyUrl = env('PROXY_PATH')."?bot_key=$botKey";

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $client->request('POST', $proxyUrl, [
            'body' => $input,
        ]);

        return new Response($response->getContent(), $response->getStatusCode());
    }
}
