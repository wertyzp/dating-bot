<?php

declare(strict_types=1);

namespace App\Chat\Services;

use App\Models\Link;

class LinkService
{
    /**
     * @var array<string, array<string>>
     */
    protected const LINK_MAP = [
        Link::TYPE_TELEGRAM => ['t.me', 'telegram.me', 'www.t.me', 'www.telegram.me'],
        Link::TYPE_REDDIT => ['reddit.com', 'www.reddit.com', 'redd.it', 'www.redd.it'],
        Link::TYPE_DISCORD => ['discord.gg', 'www.discord.gg', 'discord.com', 'www.discord.com', 'discordapp.com', 'www.discordapp.com'],
        Link::TYPE_X => ['twitter.com', 'www.twitter.com', 'x.com', 'www.x.com'],
        Link::TYPE_TIKTOK => ['tiktok.com', 'www.tiktok.com', 'vm.tiktok.com', 'www.vm.tiktok.com', 'm.tiktok.com' ,'www.m.tiktok.com', 'api.tiktokv.com', 't.tiktok.com', 'yt.tiktok.com', 'vt.tiktok.com'],
        Link::TYPE_INSTAGRAM => ['instagram.com', 'www.instagram.com'],
        Link::TYPE_QUORA => ['quora.com', 'www.quora.com', 'qr.ae', 'www.qr.ae'],
        Link::TYPE_FACEBOOK => ['facebook.com', 'www.facebook.com', 'web.facebook.com', 'www.web.facebook.com', 'm.facebook.com', 'fb.watch', 'www.fb.watch', 'fb.me', 'www.fb.me'],
        Link::TYPE_YOUTUBE => ['youtube.com', 'www.youtube.com', 'youtu.be', 'www.youtu.be'],
        Link::TYPE_CMC => ['coinmarketcap.com', 'www.coinmarketcap.com'],
        Link::TYPE_BINANCE => ['binance.com', 'www.binance.com', 'm.binance.com', 'p2p.binance.com', 'www.p2p.binance.com', 'binance.me', 'www.binance.me', 'app.binance.com', 'www.app.binance.com'],
        Link::TYPE_LINKEDIN => ['linkedin.com', 'www.linkedin.com', 'm.linkedin.com', 'linked.in', 'www.linked.in'],
        Link::TYPE_THREADS => ['threads.com', 'www.threads.com', 'thread.net', 'www.thread.net'],
        Link::TYPE_STACKEXCHANGE => ['stackexchange.com', 'www.stackexchange.com'],
        // Link::TYPE_BITS_MEDIA => ['bits.media', 'www.bits.media'],
        // Link::TYPE_MMGP_RU => ['mmgp.ru', 'www.mmgp.ru'],
        // Link::TYPE_VK_COM => ['vk.com', 'www.vk.com', 'm.vk.com', 'vk.me', 'www.vk.me'],
    ];
    public function getLinksFromText(string $text): array
    {
        //Telegram, reddit, Discord, X, Tiktok, Instagram, Quora

        $map = self::LINK_MAP;

        $result = [];

        foreach (array_keys($map) as $key) {
            $result[$key] = [];
        }

        $matches = [];
        // get all links first
        preg_match_all('/https?:\/\/[^\s\n]+/', $text, $matches);
        $links = $matches[0] ?? [];

        // check by domain name, but str can contain also in body other domains
        foreach ($links as $link) {
            $host = parse_url($link, PHP_URL_HOST);
            foreach ($map as $key => $domains) {
                if (in_array($host, $domains)) {
                    $result[$key][] = $link;
                    continue 2;
                }
            }
            $result['other'][] = $link;
        }
        return $result;
    }

    public function getLinkType(string $link): string
    {
        $map = self::LINK_MAP;
        $host = parse_url($link, PHP_URL_HOST);
        foreach ($map as $key => $domains) {
            if (in_array($host, $domains)) {
                return $key;
            }
        }
        return Link::TYPE_OTHER;
    }

    public function getLinkTypes(): array
    {
        return array_keys(self::LINK_MAP);
    }
}
