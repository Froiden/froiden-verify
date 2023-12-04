<?php

namespace Froiden\Envato\Controllers;

use Froiden\Envato\Functions\EnvatoUpdate;
use Froiden\Envato\Helpers\Reply;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Zip;

class UpdateScriptVersionController extends Controller
{

    private $tmp_backup_dir = null;

    private function checkPermission()
    {
        return config('froiden_envato.allow_users_id');
    }

    /*
    * Download and Install Update.
    */
    public function update()
    {
        // Get the setting and retrieve the app setting
        $setting = config('froiden_envato.setting');
        $this->appSetting = (new $setting)::first();

        // Check if support has expired
        if (Carbon::parse($this->appSetting->supported_until)->isPast()) {
            return Reply::error('Please renew your support for one-click updates.');
        }

        // Check user permission
        if (!$this->checkPermission()) {
            return Reply::error('ACTION NOT ALLOWED.');
        }

        // Get information about the latest version
        $lastVersionInfo = $this->getLastVersion();

        // Check if the system is already updated to the latest version
        if ($lastVersionInfo['version'] <= $this->getCurrentVersion()) {
            return Reply::error('Your System IS ALREADY UPDATED to the latest version!');
        }


        try {
            // Set up temporary backup directory
            $this->tmp_backup_dir = base_path() . '/backup_' . date('Ymd');

            // Get the update name and filename
            $update_name = $lastVersionInfo['archive'];
            $filename_tmp = config('froiden_envato.tmp_path') . '/' . $update_name;

            // Delete the old file if it exists
            if (file_exists($filename_tmp)) {
                File::delete($filename_tmp);
            }

            // Clear cache when the update button is clicked
            $this->configClear();

            // Return success response with download message
            return Reply::successWithData('Starting Download...', ['description' => $lastVersionInfo['description']]);
        } catch (\Exception $e) {
            // Handle update error and try to restore to the old status
            echo '<p>ERROR DURING UPDATE (!!check the update archive!!) --TRY to restore OLD status ........... </p>';

            $this->restore();
        }
    }

    public function install()
    {
        if (!$this->checkPermission()) {
            return Reply::error("ACTION NOT ALLOWED.");
        }

        $lastVersionInfo = $this->getLastVersion();
        $archive = $lastVersionInfo['archive'];

        $update_path = config('froiden_envato.tmp_path') . '/' . $archive;

        $this->changePhpConfigs();

        $zip = Zip::open($update_path);

        // extract whole archive
        $zip->extract(base_path());
        $this->clean();

        return Reply::success('Zip extracted successfully. Now installing...');
    }

    /*
    * Download Update from $update_baseurl to $tmp_path (local folder).
    */
    public function download(Request $request)
    {

        if (!$this->checkPermission()) {
            return Reply::error("ACTION NOT ALLOWED.");
        }

        File::put(public_path() . '/percent-download.txt', '');

        $getLastVersionFileUrl = $this->getLastVersionFileUrl();

        if ($getLastVersionFileUrl['type'] == 'error') {
            return Reply::error($getLastVersionFileUrl['message']);
        }

        $update_name = $getLastVersionFileUrl['version']['file_name'];

        $filename_tmp = config('froiden_envato.tmp_path') . '/' . $update_name;

        $downloadRemoteUrl = $getLastVersionFileUrl['url'];

        $this->changePhpConfigs();

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


        return Reply::success('Download complete. Now Installing...');

    }

    /*
    * Return current version (as plain text).
    */
    public function getCurrentVersion()
    {
        $version = File::get(public_path() . '/version.txt');

        return $version;
    }

    /*
    * Check if a new Update exist.
    */
    public function check()
    {
        $lastVersionInfo = $this->getLastVersion();
        if ($lastVersionInfo['version'] > $this->getCurrentVersion()) {
            return $lastVersionInfo['version'];
        }

        return '';
    }

    private function setCurrentVersion($last)
    {
        File::put(public_path() . '/version.txt', $last); //UPDATE $current_version to last version
    }

    private function getLastVersion()
    {
        $url = config('froiden_envato.updater_file_path');

        return EnvatoUpdate::getRemoteData($url);
    }

    private function backup($filename)
    {
        $backup_dir = $this->tmp_backup_dir;

        if (!is_dir($backup_dir)) File::makeDirectory($backup_dir, $mode = 0755, true, true);
        if (!is_dir($backup_dir . '/' . dirname($filename))) File::makeDirectory($backup_dir . '/' . dirname($filename), $mode = 0755, true, true);

        File::copy(base_path() . '/' . $filename, $backup_dir . '/' . $filename); //to backup folder
    }

    private function restore()
    {
        if (!isset($this->tmp_backup_dir))
            $this->tmp_backup_dir = base_path() . '/backup_' . date('Ymd');

        try {
            $backup_dir = $this->tmp_backup_dir;
            $backup_files = File::allFiles($backup_dir);

            foreach ($backup_files as $file) {
                $filename = (string)$file;
                $filename = substr($filename, (strlen($filename) - strlen($backup_dir) - 1) * (-1));
                echo $backup_dir . '/' . $filename . " => " . base_path() . '/' . $filename;
                File::copy($backup_dir . '/' . $filename, base_path() . '/' . $filename); //to respective folder
            }

        } catch (\Exception $e) {
            echo "Exception => " . $e->getMessage();
            echo "<BR>[ FAILED ]";
            echo "<BR> Backup folder is located in: <i>" . $backup_dir . "</i>.";
            echo "<BR> Remember to restore System UP-Status through shell command: <i>php artisan up</i>.";

            return false;
        }

        echo "[ RESTORED ]";

        return true;
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

    public function downloadPercent(Request $request)
    {
        $percent = File::get(public_path() . '/percent-download.txt');

        return $percent;
    }

    public function checkIfFileExtracted()
    {
        $lastVersionInfo = $this->getLastVersion();
        if ($lastVersionInfo['version'] == $this->getCurrentVersion()) {

            $status = Artisan::call('migrate:check');

            if ($status) {
                sleep(3);
                Artisan::call('migrate', array('--force' => true)); //migrate database
            }
            $lastVersionInfo = $this->getLastVersion();
            $this->setCurrentVersion($lastVersionInfo['version']); //update system version

            //logout user after installing update
            Auth::logout();

            return Reply::success('Installed successfully.');
        }
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

    public function updateDatabase()
    {
        Artisan::call('migrate', array('--force' => true));

        return 'Database updated successfully. <a href="' . route(config('froiden_envato.redirectRoute')) . '">Click here to Login</a>';
    }

    public function clearCache()
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

    private function changePhpConfigs()
    {
        try {
            if (function_exists('ini_set')) {
                // set unlimited
                ini_set('max_execution_time', 0);
                ini_set('memory_limit', -1);
            }
        } catch (\Exception $e) {
            $e->getMessage();
        }
    }


    private function getLastVersionFileUrl()
    {
        $setting = config('froiden_envato.setting');

        $this->appSetting = (new $setting)::first();

        // Change last_license_verified_at to 1 day back so as when he logs in back. The license is checked again
        if (Schema::hasColumn($this->appSetting->getTable(), 'last_license_verified_at')) {
            $this->appSetting->update(['last_license_verified_at' => now()->subDays(2)]);
        }

        $url = config('froiden_envato.latest_version_file') . '/' . $this->appSetting->purchase_code;

        return EnvatoUpdate::getRemoteData($url);

    }
    
}
