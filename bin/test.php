<?php

    function getRedditPostLength(string $link): int|false
    {
        $parts = parse_url($link);

        $link = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
        $link = rtrim($link, '/');
        $url = "$link.json";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Reddit requires a User-Agent header
        curl_setopt($ch, CURLOPT_USERAGENT, 'RedditPostLengthScript/1.0 by wertyzp');

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data[0]['data']['children'][0]['data']['selftext'])) {
            return false;
        }

        $selftext = $data[0]['data']['children'][0]['data']['selftext'];
        $length = strlen($selftext);

        return $length;
    }

    echo getRedditPostLength('https://www.reddit.com/r/TokenFinders/comments/1j2yspm/swisstronik_pioneering_compliance_and_privacy_in/');

?>
