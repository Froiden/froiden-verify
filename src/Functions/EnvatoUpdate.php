<?php

namespace Froiden\Envato\Functions;

use Froiden\Envato\Helpers\FroidenApp;
use GuzzleHttp\Client;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\File;

class EnvatoUpdate
{

    public static function companySetting()
    {
        $setting = config('froiden_envato.setting');

        return (new $setting)::first();
    }


    public static function showReview()
    {
        $setting = config('froiden_envato.setting');
        $envatoUpdateCompanySetting = (new $setting)::first();

        // Check conditions for showing the review modal
        return (
            !is_null($envatoUpdateCompanySetting->supported_until) &&
            !$envatoUpdateCompanySetting->supportedUntilIsPast() &&
            (
                $envatoUpdateCompanySetting->isWithin175Days() ||
                $envatoUpdateCompanySetting->isBeyond200DaysAndWithin360Days()
            ) &&
            $envatoUpdateCompanySetting->show_review_modal === 1
        );
    }


    /**
     * Check if the supported_until date is in the past.
     *
     * @return bool
     */
    public function supportedUntilIsPast()
    {
        return Carbon::parse($this->supported_until)->isPast();
    }

    /**
     * Check if the supported_until date is within 175 days.
     *
     * @return bool
     */
    public function isWithin175Days()
    {
        return Carbon::parse($this->supported_until)->diffInDays(Carbon::now()) <= 175;
    }

    /**
     * Check if the supported_until date is beyond 200 days and within 360 days.
     *
     * @return bool
     */
    public function isBeyond200DaysAndWithin360Days()
    {
        $daysDifference = Carbon::parse($this->supported_until)->diffInDays(Carbon::now());

        return $daysDifference > 200 && $daysDifference <= 360;
    }

    public static function reviewUrl()
    {
        $setting = config('froiden_envato.setting');
        $envatoUpdateCompanySetting = (new $setting)::first();

        $url = str_replace('verify-purchase', 'review', config('froiden_envato.verify_url'));

        return $url . '/' . $envatoUpdateCompanySetting->purchase_code;

    }

    public static function plugins()
    {
        return FroidenApp::getRemoteData(config('froiden_envato.plugins_url'));
    }

    public static function updateVersionInfo()
    {
        $updateVersionInfo = [];
        try {
            // Get Data from server for download files
            $lastVersion = FroidenApp::getRemoteData(config('froiden_envato.updater_file_path'));

            if ($lastVersion['version'] > File::get('version.txt')) {
                $updateVersionInfo['lastVersion'] = $lastVersion['version'];
                $updateVersionInfo['updateInfo'] = $lastVersion['description'];
            }
            $updateVersionInfo['updateInfo'] = $lastVersion['description'];

        } catch (\Exception $e) {
            $e->getMessage();
        }

        try {
            // Get data of Logs

            $lastVersionLog = FroidenApp::getRemoteData(config('froiden_envato.versionLog') . '/' . File::get('version.txt'));

            foreach ($lastVersionLog as $item) {
                // Ignore duplicate of latest version
                $releaseDate = $item['release_date'] ? ' (Release date: ' . Carbon::parse($item['release_date'])->format('d M Y') . ')' : '';
                if (version_compare($item['version'], $lastVersion['version']) == 0) {
                    $updateVersionInfo['updateInfo'] = '<strong class="version-update-heading">Version: ' . $item['version'] . $releaseDate . '</strong>' . $item['description'];
                    continue;
                };

                $updateVersionInfo['updateInfo'] .= '<strong class="version-update-heading">Version: ' . $item['version'] . $releaseDate . '</strong>' . $item['description'];
            }
        } catch (\Exception $e) {
            $e->getMessage();
        }

        $updateVersionInfo['appVersion'] = File::get('version.txt');
        $laravel = app();
        $updateVersionInfo['laravelVersion'] = $laravel::VERSION;

        return $updateVersionInfo;
    }


    public static function curl($postData)
    {
        // Verify purchase

       try {
           $ch = curl_init();
           curl_setopt($ch, CURLOPT_URL, config('froiden_envato.verify_url'));

           curl_setopt($ch, CURLOPT_POST, 1);
           curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
           // Object Object Error for verification
           curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
           curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

           curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
           $server_output = curl_exec($ch);
           $response = json_decode($server_output, true);
           curl_close($ch);

           return $response;
       } catch (\Exception $e) {

           return [
               'status' => 'success',
               'messages' => 'Your purchase code is successfully verified'. $e->getMessage()
           ];
       }
    }

}
