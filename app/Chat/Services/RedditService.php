<?php

declare(strict_types=1);

namespace App\Chat\Services;

use Illuminate\Support\Facades\Cache;
use League\OAuth2\Client\Token\AccessToken;

class RedditService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $username;
    protected string $password;
    protected string $userAgent;
    public function __construct()
    {
        $this->clientId = config('services.reddit.client_id');
        $this->clientSecret = config('services.reddit.client_secret');
        $this->username = config('services.reddit.username');
        $this->password = config('services.reddit.password');
        $this->userAgent = config('services.reddit.user_agent');
    }

    protected function getAccessToken(): AccessToken
    {
        /** @var AccessToken $accessToken */
        $accessTokenCache = Cache::get('access_token');
        if ($accessTokenCache) {
            $accessToken = unserialize($accessTokenCache);
            if (!$accessToken->hasExpired()) {
                return $accessToken;
            }
        }

        $clientId = $this->clientId;
        $clientSecret = $this->clientSecret;
        $username = $this->username;
        $password = $this->password;
        $userAgent = $this->userAgent;
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://www.reddit.com/api/v1/access_token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $userAgent,
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
            throw new \RuntimeException("cURL error: " . curl_error($curl));
        }
        curl_close($curl);
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            throw new \RuntimeException("Error fetching access token: " . $data['error']);
        }
        AccessToken::setTimeNow(time());
        $accessToken = new AccessToken($data);
        Cache::set('access_token', serialize($accessToken), $data['expires_in'] - 60);
        return $accessToken;
    }

    /**
     * Fetches the Reddit post data from the given URL.
     *
     * @param string $url The Reddit post URL.
     * @return array The decoded JSON response from Reddit.
     * @throws \InvalidArgumentException If the URL is invalid or not a Reddit URL.
     * @throws \RuntimeException If there is a cURL error.
     */

    public function get(string $url): array
    {
        // Remove query string and hash fragment
        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['scheme']) || !isset($parsedUrl['host']) || !isset($parsedUrl['path'])) {
            throw new \InvalidArgumentException('Invalid URL');
        }

        if (!in_array($parsedUrl['host'], ['www.reddit.com', 'reddit.com'])) {
            throw new \InvalidArgumentException('Invalid Reddit URL');
        }

        // change host to oauth.reddit.com
        $parsedUrl['scheme'] = 'https';
        $parsedUrl['host'] = 'oauth.reddit.com';
        // Reconstruct clean URL
        $cleanUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];

        // Ensure it ends with a slash
        if (substr($cleanUrl, -1) !== '/') {
            $cleanUrl .= '/';
        }

        // Append .json
        $jsonUrl = $cleanUrl . '.json';
        $accessToken = $this->getAccessToken()->getToken();
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $jsonUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                "Authorization: bearer $accessToken"
            ],
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \RuntimeException("cURL error: " . curl_error($ch));
        }
        curl_close($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // check for redirect
        if ($code >= 300 && $code < 400) {
            throw new RedditService\RedirectionException(
                "Redirection error: $code for URL $jsonUrl"
            );
        }
        $result = json_decode($response, true);
        if (!is_array($result)) {
            throw new \RuntimeException("Invalid response from Reddit API");
        }
        return $result;
    }

    public function getLinkAnalytics(string $url): ?array
    {
        return $this->getAnalytics($this->get($url));

    }

    public function getAnalytics(array $data): ?array
    {
        if (!isset($data[0]['data']['children'][0]['data'])) {
            return null;
        }

        $post = $data[0]['data']['children'][0]['data'];

        $created = null;
        if (!empty($post['created_utc'])) {
            $created = (new \DateTime("@{$post['created_utc']}", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }
        return [
            'ups' =>  $post['ups'] ?? 0,
            'downs' => $post['downs'] ?? 0,
            'created' => $created,
            'title' => $post['title'] ?? '',
            'author' => $post['author'] ?? '',
            'score' => $post['score'] ?? '',
            'num_comments' => $post['num_comments'] ?? '',
            'upvote_ratio' => $post['upvote_ratio'] ?? '',
            'crosspost' => isset($post['crosspost_parent_list']),
        ];
    }

    public function isPostAccessible(array $data): bool
    {
        if (!isset($data[0]['data']['children'][0]['data'])) {
            return false;
        }

        $post = $data[0]['data']['children'][0]['data'];

        if (
            isset($post['removed_by_category']) ||
            $post['selftext'] === '[removed]' ||
            $post['selftext'] === '[deleted]'
        ) {
            return false;
        }

        return true;
    }

    public function isLinkAccessible(string $url): bool
    {
        $data = $this->get($url);
        return $this->isPostAccessible($data);
    }

}
