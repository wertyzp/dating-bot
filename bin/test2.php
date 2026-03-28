<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

chdir(dirname(__DIR__));

$app = require 'bootstrap/app.php';

function isRedditPostAccessible($url): bool
    {
        // Remove query string and hash fragment
        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['scheme']) || !isset($parsedUrl['host']) || !isset($parsedUrl['path'])) {
            echo  "Invalid URL: $url\n";
            return true; // invalid URL, treat as removed
        }

        // Reconstruct clean URL
        $cleanUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];

        // Ensure it ends with a slash
        if (substr($cleanUrl, -1) !== '/') {
            $cleanUrl .= '/';
        }

        // Append .json
        $jsonUrl = $cleanUrl . '.json';

        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: RedditCheckerBot/1.0\r\n"
            ]
        ]);

        $response = @file_get_contents($jsonUrl, false, $context);

        if ($response === false) {
            echo "Failed to fetch URL: $jsonUrl\n";
            return true; // Treat fetch failure as valid
        }

        $data = json_decode($response, true);

        if (!isset($data[0]['data']['children'][0]['data'])) {
            echo "Invalid response structure for URL: $jsonUrl\n";
            return false;
        }

        $post = $data[0]['data']['children'][0]['data'];

        if (
            isset($post['removed_by_category']) ||
            $post['selftext'] === '[removed]' ||
            $post['selftext'] === '[deleted]'
        ) {
            echo "Post is removed or deleted: $url\n";
            return false;
        }

        echo "All ok\n";
        return true;
    }

$url = 'https://www.reddit.com/r/CryptoMarsShots/comments/1jtciv6/ruvi_ai_powering_the_future_of_decentralized_ai/';
$result = isRedditPostAccessible($url);
if ($result) {
    echo "URL is accessible\n";
} else {
    echo "URL is not accessible\n";
}
