<?php

namespace Froiden\Envato\Traits;

use Froiden\Envato\Functions\EnvatoUpdate;
use Froiden\Envato\Helpers\FroidenApp;
use Froiden\Envato\Helpers\Reply;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

trait ModuleVerify
{

    private $appSetting;
    private $reply;

    private function setSetting($module)
    {
        $setting = config($module . '.setting');
        $this->appSetting = (new $setting)::first();
    }

    /**
     * @param mixed $module
     * @return bool
     * Check if Purchase code is stored in settings table and is verified
     */
    public function isModuleLegal($module)
    {
        return $this->isLocalHost($module);
    }

    public function isLocalHost($module)
    {
        // Check if verification is required for this module or not
        if (!config($module . '.verification_required')) {
            return true;
        }

        if (FroidenApp::isLocalHost()) {
            return true;
        }

        return false;

    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * Show verify page for verification
     */
    // phpcs:ignore
    public function verifyModulePurchase($module)
    {
        return view('custom-modules.ajax.verify', compact('module'));
    }

    /**
     *
     * @param mixed $module
     * @param mixed $purchaseCode
     * @return array|string[]
     */
    public function modulePurchaseVerified($module, $purchaseCode = null)
    {
        $this->setSetting($module);

        if ($this->isLocalHost($module)) {
            $this->saveToModuleSettings($purchaseCode, $module);

            return Reply::successWithData('Module verified for localhost' . ' <a href="">Click to go back</a>', []);
        }

        if (!is_null($purchaseCode)) {
            return $this->getServerData($purchaseCode, $module);
        }

        return $this->getServerData($this->appSetting->purchase_code, $module, false);
    }

    /**
     * @param mixed $purchaseCode
     * @param mixed $module
     */
    public function saveToModuleSettings($purchaseCode, $module)
    {
        $this->setSetting($module);
        $setting = $this->appSetting;
        $setting->purchase_code = $purchaseCode;
        $setting->save();
    }

    public function saveSupportModuleSettings($response, $module): void
    {
        $this->setSetting($module);

        $this->updateColumnIfChanged('supported_until', $response);
        $this->updateColumnIfChanged('purchased_on', $response);
        $this->updateColumnIfChanged('license_type', $response);

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
     *
     * @param mixed $purchaseCode
     * @param mixed $module
     * @param boolean $savePurchaseCode
     * @return object
     */
    private function getServerData($purchaseCode, $module, $savePurchaseCode = true)
    {
        $version = File::get(module_path($module) . '/version.txt');

        $postData = [
            'purchaseCode' => $purchaseCode,
            'domain' => \request()->getHost(),
            'itemId' => config($module . '.envato_item_id'),
            'appUrl' => urlencode(url()->full()),
            'version' => $version,
        ];

        // Send request to froiden server to validate the license
        $response = EnvatoUpdate::curl($postData);

        $this->saveSupportModuleSettings($response, $module);

        if ($response['status'] === 'success') {

            if ($savePurchaseCode) {
                $this->saveToModuleSettings($purchaseCode, $module);
            }

            return Reply::successWithData($response['message'] . ' <a href="">Click to go back</a>', ['server' => $response]);
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

}
