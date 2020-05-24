<?php

namespace Froiden\Envato\Traits;

use Froiden\Envato\Helpers\Reply;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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

        // Return true if its running on test domain of .dev domain
       if (strpos($domain, '.test') !== false || strpos($domain, '.dev') !== false || strpos($domain, '.app') !== false) {
            return true;
        }

        if (is_null($this->appSetting->purchase_code)) {
            return false;
        }
        $version = File::get(public_path('version.txt'));
        $data = [
            'purchaseCode' => $this->appSetting->purchase_code,
            'email' => '',
            'domain' => $domain,
            'itemId' => config('froiden_envato.envato_item_id'),
            'appUrl' => urlencode(url()->full()),
            'version' => $version,
        ];

        if($data['itemId'] =='23263417' || $data['itemId']=='20052522'){
            $data['email'] = $this->appSetting->company_email;
        }

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

    public function showInstall()
    {
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            echo view('vendor.froiden-envato.install_message');
            exit(1);
        }
    }

    /**
     * @param $type
     * Type = closed_permanently_button_pressed,already_reviewed_button_pressed
     *
     */
    public function hideReviewModal($buttonPressedType)
    {
        $this->setSetting();
        $this->appSetting->show_review_modal = 0;
        $this->appSetting->save();
        if(is_null($this->appSetting->purchase_code)){
            return [
                'status' => 'success',
                'code' => '000',
                'messages' => 'Thank you'
            ];
        }
        return $this->curlReviewContent($buttonPressedType);
    }

    public function curlReviewContent($buttonPressedType)
    {
        // Verify purchase
        try {
            $url = str_replace('verify-purchase','button-pressed',config('froiden_envato.verify_url'));
            $url = $url.'/'.$this->appSetting->purchase_code.'/'.$buttonPressedType;

            $client = new Client();
            $response = $client->request('GET', $url);
            $statusCode = $response->getStatusCode();
            $content = $response->getBody();
            return json_decode($response->getBody(), true);

        } catch (\Exception $e) {

            return [
                'status' => 'success',
                'code' => 'catch',
                'messages' => 'Thank you'
            ];
        }

    }
}
