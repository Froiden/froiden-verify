<?php

namespace Froiden\Envato\Controllers;

use App\Helper\Reply;
use App\Http\Controllers\Controller;
use Froiden\Envato\Functions\EnvatoUpdate;
use Froiden\Envato\Helpers\FroidenApp;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Macellan\Zip\Zip;

class UpdateScriptVersionController extends Controller
{

    private $tmp_backup_dir = null;

    public function __construct()
    {
        parent::__construct();
        // Get the setting and retrieve the app setting
        $setting = config('froiden_envato.setting');
        $this->appSetting = null;

        $this->changePhpConfigs();
        $module = request()->route('module');

        if ($module) {
            $this->moduleSetting($module);
        }
        else {
            $this->appSetting = $setting::first();
        }
    }

    private function moduleSetting($module)
    {
        $settingInstance = config(strtolower($module) . '.setting');

        return $this->appSetting = $settingInstance::first();

    }

    private function checkPermission()
    {
        return config('froiden_envato.allow_users_id');
    }

    public function clean()
    {
        $user = auth()->id();
        $this->configClear();

        session()->flush();
        cache()->flush();

        // login user
        auth()->loginUsingId($user);
    }

    public function configClear()
    {
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');
    }

    private function changePhpConfigs()
    {
        try {
            ini_set('max_execution_time', 0); // Set unlimited execution time
            ini_set('memory_limit', -1);      // Set unlimited memory limit
        } catch (\Exception $e) {
            // Log or report the exception message
            logger()->error('Error changing PHP configurations: ' . $e->getMessage());
        }
    }

    public function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        }
        elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        }
        elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        }
        elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        }
        elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        }
        else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    public function updateDatabase()
    {
        Artisan::call('migrate', array('--force' => true));

        return 'Database updated successfully. <a href="' . route(config('froiden_envato.redirectRoute')) . '">Click here to Login</a>';
    }

    public function clearCache(): array|string
    {
        $this->configClear();

        session()->flush();
        cache()->flush();

        if (request()->ajax()) {
            return Reply::success('Cache cleared successfully.');
        }

        return 'Cache cleared successfully. <a href="' . route(config('froiden_envato.redirectRoute')) . '">Click here to Login</a>';
    }

    public function refreshCache()
    {
        Artisan::call('optimize');
        Artisan::call('route:clear');

        if (request()->ajax()) {
            return Reply::success('Cache refreshed successfully.');
        }

        return 'Cache refreshed successfully. <a href="' . route(config('froiden_envato.redirectRoute')) . '">Click here to Login</a>';
    }

    public function downloadPercent($module = null)
    {
        if (!File::exists(public_path() . '/percent-download.txt')) {
            return true;
        }

        return File::get(public_path() . '/percent-download.txt');
    }

    private function setCurrentVersion($last, $module = null)
    {
        if ($module) {
            File::put(module_path($module, 'version.txt'), $last); // UPDATE $current_version to last version

            return;
        }

        File::put(public_path() . '/version.txt', $last); // UPDATE $current_version to last version
    }

    /*
    * Download and Install Update.
    */
    public function update($module = null)
    {
        // Check if support has expired
        if (Carbon::parse($this->appSetting->supported_until)->isPast()) {
            if (is_null($module) || str_contains($module, 'Universal')) {
                return Reply::error('Please renew your support for one-click updates.');
            }
        }

        // Check user permission
        if (!$this->checkPermission()) {
            return Reply::error('ACTION NOT ALLOWED.');
        }

        try {
            // Get information about the latest version
            $lastVersionInfo = $this->getLastVersion($module);

            // Check if the system is already updated to the latest version
            if ($lastVersionInfo['version'] <= $this->getCurrentVersion($module)) {
                return Reply::error('You ALREADY on the latest version!');
            }

            // Set up temporary backup directory
            $this->tmp_backup_dir = base_path() . '/backup_' . date('Ymd');

            if ($module) {
                $this->tmp_backup_dir = $this->tmp_backup_dir . '_' . $module;
            }

            // Get the update name and filename
            $update_name = $lastVersionInfo['archive'];
            $filename_tmp = config('froiden_envato.tmp_path') . '/' . $update_name;

            // Delete the old file if it exists
            if (file_exists($filename_tmp)) {
                File::delete($filename_tmp);
            }

            // Clear cache when the update button is clicked
            // $this->configClear();

            // Return success response with download message
            return Reply::successWithData('Starting Download...', ['description' => $lastVersionInfo['description']]);
        } catch (\Exception $e) {
            // Handle update error and try to restore to the old status
            return Reply::error('ERROR DURING UPDATE : ' . $e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function install($module = null)
    {
        if (!$this->checkPermission()) {
            return Reply::error('ACTION NOT ALLOWED.');
        }

        try {
            $lastVersionInfo = $this->getLastVersion($module);
            $archive = $lastVersionInfo['archive'];

            $update_path = config('froiden_envato.tmp_path') . '/' . $archive;

            $zip = Zip::open($update_path);

            $path = base_path();

            if ($module) {
                $path = base_path('Modules/');
            }

            // extract whole archive
            $zip->extract($path);

            $this->clean();

            File::delete(public_path() . '/percent-download.txt');
        } catch (\Exception $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::success('Zip extracted successfully. Now installing...');
    }

    /*
    * Download Update from $update_baseurl to $tmp_path (local folder).
    */
    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    public function download($module = null)
    {
        if (!$this->checkPermission()) {
            return Reply::error('ACTION NOT ALLOWED.');
        }

        try {
            File::put(public_path() . '/percent-download.txt', '');

            $getLastVersionFileUrl = $this->getLastVersionFileUrl($module);

            if ($getLastVersionFileUrl['type'] == 'error') {
                return Reply::error($getLastVersionFileUrl['message']);
            }

            $update_name = $getLastVersionFileUrl['version']['file_name'];

            $filename_tmp = config('froiden_envato.tmp_path') . '/' . $update_name;

            $downloadRemoteUrl = $getLastVersionFileUrl['url'];

            $dlHandler = fopen($filename_tmp, 'w');

            $client = new Client();
            $client->request('GET', $downloadRemoteUrl, [
                'sink' => $dlHandler,
                'progress' => function ($dl_total_size, $dl_size_so_far, $ul_total_size, $ul_size_so_far) {
                    $percentDownloaded = ($dl_total_size > 0) ? (($dl_size_so_far / $dl_total_size) * 100) : 0;
                    File::put(public_path() . '/percent-download.txt', $percentDownloaded);
                },
                'verify' => false
            ]);
        } catch (\Exception $e) {
            return Reply::error($e->getMessage());
        }

        // Check if file is downloaded
        if (!file_exists($filename_tmp)) {
            return Reply::error('Download failed! Please try again.');
        }

        // Check if file is valid zip
        if (!Zip::check($filename_tmp)) {
            return Reply::error('Download failed! Please try again.');
        }

        return Reply::success('Download complete. Now Installing...');

    }

    public function checkIfFileExtracted($module = null)
    {
        $lastVersionInfo = $this->getLastVersion($module);

        if ($lastVersionInfo['version'] == $this->getCurrentVersion($module)) {

            $status = Artisan::call('migrate:check');

            if ($status) {
                Artisan::call('migrate', array('--force' => true)); // migrate database
            }

            $this->setCurrentVersion($lastVersionInfo['version'], $module); // update system version

            // logout user after installing update
            $this->clean();

            return Reply::success('Installed successfully.');
        }
    }

    private function getLastVersion($module = null)
    {
        $url = config('froiden_envato.updater_file_path');

        if ($module) {
            $url = $this->replaceWithModuleProductName($module, $url);
        }

        return FroidenApp::getRemoteData($url);
    }

    /*
    * Return current version (as plain text).
    */
    public function getCurrentVersion($module = null)
    {
        if ($module) {
            return File::get(module_path($module, 'version.txt'));
        }

        return File::get(public_path() . '/version.txt');
    }

    /*
    * Check if a new Update exist.
    */
    public function check($module = null)
    {
        $lastVersionInfo = $this->getLastVersion($module);

        if ($lastVersionInfo['version'] > $this->getCurrentVersion($module)) {
            return $lastVersionInfo['version'];
        }

        return '';
    }

    private function getLastVersionFileUrl($module = null)
    {
        // Change last_license_verified_at to 1 day back so as when he logs in back. The license is checked again
        if (Schema::hasColumn($this->appSetting->getTable(), 'last_license_verified_at')) {
            $this->appSetting->update(['last_license_verified_at' => now()->subDays(2)]);
        }

        $lastVersionInfo = $this->getLastVersion($module);
        $archive = $lastVersionInfo['archive'];

        $url = config('froiden_envato.latest_version_file') . '/' . $this->appSetting->purchase_code . '/' . $archive;

        if ($module) {
            $url = $this->replaceWithModuleItemId($module, $url);
        }

        return FroidenApp::getRemoteData($url);

    }

    public function refresh($module = null)
    {
        if (FroidenApp::isLocalHost()) {
            return Reply::error('You are on localhost. You need to be on live domain to refresh support.');
        }

        $itemId = config('froiden_envato.envato_item_id');

        if ($module) {
            $itemId = config(strtolower($module) . '.envato_item_id');
        }

        $data = [
            'purchaseCode' => $this->appSetting->purchase_code,
            'email' => '',
            'domain' => request()->getHost(),
            'itemId' => $itemId,
            'appUrl' => urlencode(url()->full()),
            'version' => $this->getCurrentVersion($module),
        ];

        $response = EnvatoUpdate::curl($data);

        if (!$response) {
            return Reply::error('Something went wrong. Please try again.');
        }

        $message = $response['message'] ?? 'Refreshed successfully.';

        if (isset($response['code']) && $response['code'] != 400) {
            return Reply::error($message);
        }

        $this->saveSupportSettings($response);

        return Reply::success($message);
    }

    public function saveSupportSettings($response)
    {
        if (isset($response['supported_until']) && ($response['supported_until'] !== $this->appSetting->supported_until)) {
            $this->appSetting->supported_until = $response['supported_until'];
        }

        if (Schema::hasColumn($this->appSetting->getTable(), 'license_type') && isset($response['license_type'])) {
            if ($response['license_type'] !== $this->appSetting->license_type) {
                $this->appSetting->license_type = $response['license_type'] ?? null;
            }
        }

        $this->appSetting->save();
    }

    public function checkSupport($module = null)
    {


        $link = 'https://froiden.freshdesk.com/support/solutions/articles/43000554421-update-application-manually';

        if ($module) {
            $link = 'https://froiden.freshdesk.com/support/solutions/articles/43000569531-module-installation';
        }

        $messageUpdateManually = '<br><br> You can still update the application manually by following the documentation <a href="' . $link . '" target="_blank">Update Application Manually</a>';

        if (is_null($this->appSetting->supported_until)) {
            return Reply::error('Please provide the accurate purchase code initially, as we currently lack the correct information for the supported until date.' . $messageUpdateManually);
        }

        $supportedUntil = Carbon::parse($this->appSetting->supported_until);

        if ($supportedUntil->isPast()) {
            if (is_null($module) || str_contains($module, 'Universal')) {
                return Reply::error('Your support has been expired on <b>' . $supportedUntil->format(global_setting()->date_format ?? 'Y-m-d') . '</b>. <br> Please renew your support for one-click updates.' . $messageUpdateManually);
            }
        }

        return Reply::success('Update available.');
    }

    private function replaceWithModuleItemId($module, $string)
    {
        $appIemId = config('froiden_envato.envato_item_id');
        $moduleItemId = config(strtolower($module) . '.envato_item_id');

        return str_replace($appIemId, $moduleItemId, $string);
    }

    private function replaceWithModuleProductName($module, $string)
    {
        $appProductName = config('froiden_envato.envato_product_name');
        $moduleProductName = config(strtolower($module) . '.script_name');

        return str_replace($appProductName, $moduleProductName, $string);
    }

    public function notify(Request $request, $module)
    {
        $this->appSetting->update(['notify_update' => $request->status]);

        return Reply::success('Notification settings updated successfully.');
    }

}
