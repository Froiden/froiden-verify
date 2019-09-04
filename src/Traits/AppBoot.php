<?php

namespace Froiden\Envato\Traits;

use Froiden\Envato\Helpers\Reply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

trait AppBoot
{

    private $appSetting;
    private $reply;


    private function setSetting()
    {
        $setting = config('froiden_envato.setting');
        $this->appSetting = (new $setting)::first();
    }

    /**
     * @return bool
     * Check if Purchase code is stored in settings table and is verified
     */
    public function isLegal()
    {
        $this->setSetting();
        $domain = \request()->getHost();

        if ($domain == 'localhost' || $domain == '127.0.0.1' || $domain == '::1') {
            return true;
        }

        if (is_null($this->appSetting->purchase_code)) {
            return false;
        }
        $version = File::get(public_path('version.txt'));
        $data = [
            'purchaseCode' => $this->appSetting->purchase_code,
            'domain' => $domain,
            'itemId' => config('froiden_envato.envato_item_id'),
            'appUrl' => urlencode(url()->full()),
            'version' => $version,
        ];

        $response = $this->curl($data);
        $this->saveSupportSettings($response);

        if ($response['status'] == 'success') {
            return true;
        }

        return false;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * Show verify page for verification
     */
    public function verifyPurchase()
    {
        return view('vendor.froiden-envato.verify_purchase');
    }

    /**
     * @param Request $request
     * @return array
     * Send request on froiden envato server to validate
     */
    public function purchaseVerified(Request $request)
    {
        $this->setSetting();
        if ($request->has('purchase_code')) {

            $request->validate([
                'purchase_code' => 'required|max:80',
            ]);

            return $this->getServerData($request->purchase_code);
        }

        return $this->getServerData($this->appSetting->purchase_code, false);
    }


    /**
     * @param $purchaseCode
     */
    public function saveToSettings($purchaseCode)
    {
        $this->setSetting();
        $setting = $this->appSetting;
        $setting->purchase_code = $purchaseCode;
        $setting->save();
    }

    public function saveSupportSettings($response)
    {
        $this->setSetting();
        if (isset($response['supported_until']) && ($response['supported_until'] !== $this->appSetting->supported_until)) {
            $this->appSetting->supported_until = $response['supported_until'];
            $this->appSetting->save();
        }
    }

    /**
     * @param $postData
     * @return mixed
     * Curl post to the server
     */
    public function curl($postData)
    {
        // Verify purchase

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, config('froiden_envato.verify_url'));

            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);
            $response = json_decode($server_output, true);
            curl_close($ch);

            return $response;

        } catch (\Exception $e) {

            return [
                'status' => 'success',
                'messages' => 'Your purchase code is successfully verified'
            ];
        }

    }

    /**
     * @param $purchaseCode
     * @param bool $savePurchaseCode
     * @return mixed
     */
    private function getServerData($purchaseCode, $savePurchaseCode = true)
    {
        $version = File::get(public_path('version.txt'));

        $postData = [
            'purchaseCode' => $purchaseCode,
            'domain' => \request()->getHost(),
            'itemId' => config('froiden_envato.envato_item_id'),
            'appUrl' => urlencode(url()->full()),
            'version' => $version,
        ];

        // Send request to froiden server to validate the license
        $response = $this->curl($postData);

        $this->saveSupportSettings($response);

        if ($response['status'] === 'success') {

            if ($savePurchaseCode) {
                $this->saveToSettings($purchaseCode);
            }

            return Reply::successWithData($response['message'] . ' <a href="' . route(config('froiden_envato.redirectRoute')) . '">Click to go back</a>', ['server' => $response]);
        }

        return Reply::error($response['message'], null, ['server' => $response]);
    }

    public function showInstall(){
        $setting = config('froiden_envato.setting');
        $settingTable = (new $setting)->getTable();

        if(!Schema::hasTable($settingTable)){
            echo view('vendor.froiden-envato.install_message');
            exit(1);
        }
    }
}
