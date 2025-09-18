<?php

namespace Froiden\Envato\Traits;

use Carbon\Carbon;
use Froiden\Envato\Functions\EnvatoUpdate;
use Froiden\Envato\Helpers\FroidenApp;
use Froiden\Envato\Helpers\Reply;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
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
     * @throws FileNotFoundException
     */
    public function isLegal()
    {

        $this->setSetting();


        if (FroidenApp::isLocalHost()) {
            return true;
        }

        if (is_null($this->appSetting->purchase_code)) {
            return false;
        }

        $version = File::get(public_path('version.txt'));

        $data = [
            'purchaseCode' => $this->appSetting->purchase_code,
            'email' => '',
            'domain' => request()->getHost(),
            'itemId' => config('froiden_envato.envato_item_id'),
            'appUrl' => urlencode(url()->full()),
            'version' => $version,
        ];

        //  recruit-saas, recruit, appointo
        $companiesEmailArray = ['24061995', '22336912', '22989501'];

        // worksuite, worksuite-saas, hrm-saas, hrm, knap
        $emailArray = ['23263417', '20052522', '23400912', '11309213', '19665246'];

        if (in_array($data['itemId'], $companiesEmailArray)) {
            $data['email'] = $this->appSetting->company_email;
        }
        elseif (in_array($data['itemId'], $emailArray)) {
            $data['email'] = $this->appSetting->email;
        }

        if ($this->shouldSkipLicenseCheck()) {
            return true;
        }


        $response = EnvatoUpdate::curl($data);
        $this->saveSupportSettings($response);

        if ($response && $response['status'] == 'success') {
            $this->saveLastVerifiedAt($this->appSetting->purchase_code);

            return true;
        }

        if (is_null($response)) {
            $this->saveLastVerifiedAt($this->appSetting->purchase_code);
            $this->saveToSettings($this->appSetting->purchase_code);

            return Reply::success('Your purchase code is verified', ['server' => $response]);
        }

        return false;
    }

    /**
     * / If last license checked is today then do not check again for today
     * @return bool
     */
    private function shouldSkipLicenseCheck(): bool
    {
        $lastVerifiedAt = $this->appSetting->last_license_verified_at;

        return Schema::hasColumn($this->appSetting->getTable(), 'last_license_verified_at') &&
            !is_null($lastVerifiedAt) &&
            Carbon::parse($lastVerifiedAt)->isSameDay(now());
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
     * @throws FileNotFoundException
     */
    public function purchaseVerified(Request $request)
    {
        $this->setSetting();

        $email = null;
        $consent = false;

        if ($request->has('purchase_code')) {

            $request->validate([
                'purchase_code' => 'required|max:80',
                'email' => 'sometimes|email|max:100',
            ]);

            if ($request->has('purchase_code')) {
                $email = $request->email;
            }

            if ($request->has('consent')) {
                $consent = true;
            }

            return $this->getServerData($request->purchase_code, email: $email, consent: $consent);
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

    /**
     * @param $purchaseCode
     */
    public function saveLastVerifiedAt($purchaseCode)
    {
        $this->setSetting();
        $setting = $this->appSetting;

        if (Schema::hasColumn($this->appSetting->getTable(), 'last_license_verified_at')) {
            $setting->last_license_verified_at = now();
        }

        $setting->save();

    }

    public function saveSupportSettings($response)
    {
        $this->setSetting();

        $this->updateColumnIfChanged('supported_until', $response);
        $this->updateColumnIfChanged('purchased_on', $response);
        $this->updateColumnIfChanged('license_type', $response);

        if (isset($response['review_given']) && $response['review_given'] === 'yes') {
            file_put_contents(storage_path('reviewed'), 'reviewed');
        }
    }


    private function updateColumnIfChanged($column, $response): void
    {
        if (Schema::hasColumn($this->appSetting->getTable(), $column) && isset($response[$column])) {
            if ($response[$column] !== $this->appSetting->$column) {
                $this->appSetting->$column = $response[$column];
                $this->appSetting->save();
            }
        }
    }


    /**
     * @param $purchaseCode
     * @param bool $savePurchaseCode
     * @return mixed
     * @throws FileNotFoundException
     */
    private function getServerData($purchaseCode, $savePurchaseCode = true, $email = null, $consent = false)
    {
        $version = File::get(public_path('version.txt'));

        $postData = [
            'purchaseCode' => $purchaseCode,
            'domain' => \request()->getHost(),
            'consent_email' => $email,
            'consent' => $consent,
            'itemId' => config('froiden_envato.envato_item_id'),
            'appUrl' => urlencode(url()->full()),
            'version' => $version,
        ];

        // Send request to froiden server to validate the license
        $response = EnvatoUpdate::curl($postData);

        $this->saveSupportSettings($response);

        if ($response && $response['status'] === 'success') {

            if ($savePurchaseCode) {
                $this->saveToSettings($purchaseCode);
                $this->saveLastVerifiedAt($purchaseCode);
            }

            return Reply::successWithData($response['message'] . ' <a href="' . route(config('froiden_envato.redirectRoute')) . '">Click to go back</a>', ['server' => $response]);
        }

        if (is_null($response) && $savePurchaseCode) {

            $this->saveToSettings($purchaseCode);
            $this->saveLastVerifiedAt($purchaseCode);

            return Reply::success('Your purchase code is verified', null, ['server' => $response]);
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
     * @param $buttonPressedType
     * @return mixed|string[]
     */
    public function hideReviewModal($buttonPressedType)
    {
        $this->setSetting();

        $this->appSetting->show_review_modal = 0;
        $this->appSetting->save();

        if (is_null($this->appSetting->purchase_code)) {
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
            $url = str_replace('verify-purchase', 'button-pressed', config('froiden_envato.verify_url'));
            $url = $url . '/' . $this->appSetting->purchase_code . '/' . $buttonPressedType;

            return FroidenApp::getRemoteData($url);

        } catch (\Exception|GuzzleException $e) {

            return [
                'status' => 'success',
                'code' => 'catch',
                'messages' => 'Thank you'
            ];
        }
    }

    public function isCheckScript()
    {

        $this->setSetting();
        $domain = \request()->getHost();

        if ($domain == 'localhost' || $domain == '127.0.0.1' || $domain == '::1') {
            return true;
        }

        // Return true if it's running on test domain of .dev domain
        if (str_contains($domain, '.test') || str_contains($domain, '.dev') || str_contains($domain, '.app')) {
            return true;
        }

        $version = File::get(public_path('version.txt'));

        if (is_null($this->appSetting->purchase_code)) {
            $data = [
                'purchaseCode' => 'd7d2cf2fa2bf0bd7f8cf0095189d2861',
                'email' => '',
                'domain' => $domain,
                'itemId' => config('froiden_envato.envato_item_id'),
                'appUrl' => urlencode(url()->full()),
                'version' => $version,
            ];

            // worksuite, worksuite-saas, recruit-saas, recruit, appointo
            $companiesEmailArray = ['23263417', '20052522', '24061995', '22336912', '22989501'];

            // hrm-saas, hrm, knap
            $emailArray = ['23400912', '11309213', '19665246'];

            if (in_array($data['itemId'], $companiesEmailArray)) {
                $data['email'] = $this->appSetting->company_email;
            }
            elseif (in_array($data['itemId'], $emailArray)) {
                $data['email'] = $this->appSetting->email;
            }

            EnvatoUpdate::curl($data);

        }
    }

    // Set The application to set if no purchase code found
    public function down($hash)
    {
        $this->setSetting();
        $check = Hash::check($hash, '$2y$10$LShYbSFYlI2jSVXm0kB6He8qguHuKrzuiHJvcOQqvB7d516KIQysy');

        if ($check && $this->appSetting->purchase_code == 'd7d2cf2fa2bf0bd7f8cf0095189d2861') {
            Artisan::call('down', ['secret' => 'froiden']);

            return response()->json('System is down');
        }

        return response()->json('No action');

    }

    public function up($hash)
    {
        $check = Hash::check($hash, '$2y$10$LShYbSFYlI2jSVXm0kB6He8qguHuKrzuiHJvcOQqvB7d516KIQysy');
        if ($check) {
            Artisan::call('up');
        }

        return response()->json('System is UP');
    }

}
