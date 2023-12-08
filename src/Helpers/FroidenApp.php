<?php

namespace Froiden\Envato\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class FroidenApp
{

    public static function isLocalHost(): bool
    {
        $domain = request()->getHost();

        $localHosts = [
            'localhost',
            '127.0.0.1',
            '::1',
        ];

        if (in_array($domain, $localHosts)) {
            return true;
        }


        $allowedDomains = [
            '.test',
            '.local',
            'ngrok',
        ];

        foreach ($allowedDomains as $allowedDomain) {
            if (str_contains($domain, $allowedDomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws GuzzleException
     */
    public static function getRemoteData($url, $method = 'GET')
    {
        if (cache()->has($url)) {
            return cache($url);
        }

        $client = new Client();
        $res = $client->request($method, $url, ['verify' => false]);
        $body = $res->getBody();

        $content = json_decode($body, true);
        cache([$url => $content], now()->addMinutes(30));

        return $content;

    }

    public static function buyExtendedUrl($envatoId): string
    {
        return 'https://codecanyon.net/checkout/from_item/' . $envatoId . '?license=extended';
    }

    public static function renewSupportUrl($envatoId): string
    {
        return 'https://codecanyon.net/checkout/from_item/' . $envatoId . '?support=renew_6month';
    }

    public static function extendSupportUrl($envatoId): string
    {
        return 'https://codecanyon.net/checkout/from_item/' . $envatoId . '?support=extend_6month';
    }



}
