<?php

declare(strict_types=1);

chdir(dirname(__DIR__));

$app = require 'bootstrap/app.php';




/**
$clientId = 'NZKBY2zm8iJ5ijaDQEHk7w';
$clientSecret = 'uhzzQIy6tPYtzVikAxcs2i08_q3gHA';
$username = 'Material_Choice_3270';
$password = 'reddit82pwd';

$auth = base64_encode("$clientId:$clientSecret");

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://www.reddit.com/api/v1/access_token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT => "linux:com.wertyzp.forwarder-bot:v1.0.0",
    CURLOPT_POST => true,
    CURLOPT_USERNAME => $clientId,
    CURLOPT_PASSWORD => $clientSecret,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'password',
        'username' => $username,
        'password' => $password
    ]),
]);

$response = curl_exec($curl);
if (curl_errno($curl)) {
    echo "cURL error: " . curl_error($curl);
    exit;
}
curl_close($curl);
echo "$response".PHP_EOL;
$data = json_decode($response, true);
$accessToken = $data['access_token'];

echo "Access Token: $accessToken\n";

// Example: fetch current user's info
$ch = curl_init();
curl_setopt_array($ch, [
    //CURLOPT_URL => 'https://oauth.reddit.com/api/v1/me',
    CURLOPT_URL => 'https://oauth.reddit.com/r/CryptoMarsShots/comments/1jtciv6/ruvi_ai_powering_the_future_of_decentralized_ai/.json',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT => 'linux:com.wertyzp.forwarder-bot:v1.0.0',
    CURLOPT_HTTPHEADER => [
        "Authorization: bearer $accessToken"
    ],
]);

$userInfo = curl_exec($ch);
curl_close($ch);

echo "User Info:\n$userInfo\n";
*/

$service = new \App\Chat\Services\RedditService();
/*$data = $service->isLinkAccessible(
    'https://www.reddit.com/r/CryptoMarsShots/comments/1jtciv6/ruvi_ai_powering_the_future_of_decentralized_ai/'
);*/
$data = $service->getLinkAnalytics(
    'https://www.reddit.com/r/help/comments/2ekjt8/how_do_i_make_a_word_into_a_link/'
);
var_dump($data);
