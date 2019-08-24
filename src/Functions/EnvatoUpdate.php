<?php

namespace Froiden\Envato\Functions;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;

class EnvatoUpdate {

    public static function companySetting()
    {
        $setting = config('froiden_envato.setting');
        return (new $setting)::first();
    }

    public static function updateVersionInfo()
    {
        $updateVersionInfo = [];

        try {
            $client = new Client();
            $res = $client->request('GET', config('froiden_envato.updater_file_path'), ['verify' => false]);
            $lastVersion = $res->getBody();
            $lastVersion = json_decode($lastVersion, true);

            if ($lastVersion['version'] > File::get('version.txt')) {
                $updateVersionInfo['lastVersion'] = $lastVersion['version'];
                $updateVersionInfo['updateInfo'] = $lastVersion['description'];
            }
            $updateVersionInfo['updateInfo'] = $lastVersion['description'];
        } catch (\Exception $e) {

        }
        $updateVersionInfo['appVersion'] = File::get('version.txt');

        $laravel = app();
        $updateVersionInfo['laravelVersion'] = $laravel::VERSION;

        return $updateVersionInfo;
    }


}
