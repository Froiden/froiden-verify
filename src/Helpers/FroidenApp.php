<?php

namespace Froiden\Envato\Helpers;

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

    public static function buyExtendedUrl($envatoId)
    {
        return 'https://codecanyon.net/checkout/from_item/' . $envatoId . '?license=extended';
    }

    public static function renewSupportUrl($envatoId)
    {
        return 'https://codecanyon.net/checkout/from_item/' . $envatoId . '?support=renew_6month';
    }

    public static function extendSupportUrl($envatoId)
    {
        return 'https://codecanyon.net/checkout/from_item/' . $envatoId . '?support=extend_6month';
    }

}
