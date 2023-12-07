<?php

namespace Froiden\Envato\Traits;

use App\Helper\Reply;
use Froiden\Envato\Functions\EnvatoUpdate;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Macellan\Zip\Zip;

trait UpdateVersion
{
    private $tmp_backup_dir = null;

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
            return Reply::error('Please renew your support for one-click updates.');
        }

        // Check user permission
        if (!$this->checkPermission()) {
            return Reply::error('ACTION NOT ALLOWED.');
        }

        // Get information about the latest version
        $lastVersionInfo = $this->getLastVersion($module);

        // Check if the system is already updated to the latest version
        if ($lastVersionInfo['version'] <= $this->getCurrentVersion($module)) {
            return Reply::error('You ALREADY on the latest version!');
        }

        try {
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

            // TODO: Backup the current version
            // $this->backup($module);

            // Clear cache when the update button is clicked
            $this->configClear();

            // Return success response with download message
            return Reply::successWithData('Starting Download...', ['description' => $lastVersionInfo['description']]);
        } catch (\Exception $e) {
            // Handle update error and try to restore to the old status
            echo '<p>ERROR DURING UPDATE (!!check the update archive!!) --TRY to restore OLD status ........... </p>';

            // TODO: Restore old status
            // $this->restore($module);
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

        return Reply::success('Zip extracted successfully. Now installing...');
    }

    /*
    * Download Update from $update_baseurl to $tmp_path (local folder).
    */
    public function download($module = null)
    {
        if (!$this->checkPermission()) {
            return Reply::error('ACTION NOT ALLOWED.');
        }

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
        try {
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
                sleep(3); // TODO: remove this line
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
            $url = config(strtolower($module) . '.updater_file_path');
        }

        return EnvatoUpdate::getRemoteData($url);
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
            $url = config(strtolower($module) . '.latest_version_file') . '/' . $this->appSetting->purchase_code . '/' . $archive;
        }

        return EnvatoUpdate::getRemoteData($url);

    }

    public function refresh($module = null)
    {
        $domain = \request()->getHost();
        $itemId = config('froiden_envato.envato_item_id');

        if ($module) {
            $itemId = config(strtolower($module) . '.envato_item_id');
        }

        $data = [
            'purchaseCode' => $this->appSetting->purchase_code,
            'email' => '',
            // 'domain' => $domain,
            'domain' => 'office.myredtrading.co.za',
            'itemId' => $itemId,
            'appUrl' => urlencode(url()->full()),
            'version' => $this->getCurrentVersion($module),
        ];

        $response = EnvatoUpdate::curl($data);
        info($response);
        $this->saveSupportSettings($response);

        return Reply::success('Refreshed successfully.');
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

    public function checkModuleSupport($module = null)
    {
        $supportedUntil = Carbon::parse($this->appSetting->supported_until);
        $productUrl = config('froiden_envato.envato_product_url');

        if ($module) {
            $productUrl = config(strtolower($module) . '.envato_product_url');
        }

        if ($supportedUntil->isPast()) {
            return Reply::error('Your support has been expired on <b>' . $supportedUntil->format(global_setting()->date_format ?? 'Y-m-d') . '</b>. <br> Please renew your support for one-click updates.',
            errorData: [
                'product_url' => $productUrl,
            ]);
        }

        return Reply::success('Update available.');
    }

}
