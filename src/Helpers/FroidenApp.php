<?php

namespace Froiden\Envato\Helpers;

class FroidenApp
{

    public static function isLocalHost()
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

}
