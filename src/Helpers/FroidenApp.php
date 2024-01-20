<?php

namespace Froiden\Envato\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class FroidenApp
{

    const CODECANYON_URL = 'https://codecanyon.net/checkout/from_item/';
    const CACHE_MINUTE = 30;

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

        //Ignore of IP
        if (ip2long($domain)) {
            return true;
        }

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

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);

            curl_setopt($ch, CURLOPT_POST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);
            $response = json_decode($server_output, true);
            curl_close($ch);

            cache([$url => $response], now()->addMinutes(self::CACHE_MINUTE));

            return $response;
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
    }

    public static function buyExtendedUrl($envatoId): string
    {
        return self::CODECANYON_URL . $envatoId . '?license=extended';
    }

    public static function renewSupportUrl($envatoId): string
    {
        return self::CODECANYON_URL . $envatoId . '?support=renew_6month';
    }

    public static function extendSupportUrl($envatoId): string
    {
        return self::CODECANYON_URL . $envatoId . '?support=extend_6month';
    }

}
