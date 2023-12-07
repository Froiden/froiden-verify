<?php

namespace Froiden\Envato\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Froiden\Envato\Helpers\Reply;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Macellan\Zip\Zip;

class UpdateModuleVersionController extends Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->changePhpConfigs();
    }

    private $tmp_backup_dir = null;

    private function checkPermission()
    {
        return config('froiden_envato.allow_users_id');
    }

    private function moduleSetting($module)
    {
        $settingInstance = config(strtolower($module) . '.setting');
        $fetchSetting = $settingInstance::first();
        $this->moduleSetting = $fetchSetting;
        return $fetchSetting;
    }

    public function checkModuleSupport($module)
    {
        $this->moduleSetting($module);

        $supportedUntil = Carbon::parse($this->moduleSetting->supported_until);

        if ($supportedUntil->isPast()) {
            return Reply::error('Your support has been expired on <b>' . $supportedUntil->format(global_setting()->date_format ?? 'Y-m-d') . '</b>. <br> Please renew your support for one-click updates.',
            errorData: [
                'product_url' => config(strtolower($module) . '.envato_product_url'),
            ]);
        }

        return Reply::success('Update available.');
    }

    /*
    * Download and Install Update.
    */
    public function update($module)
    {
        $this->moduleSetting($module);

        if (Carbon::parse($this->moduleSetting->supported_until)->isPast()) {
            return Reply::error('Please renew your support for one-click updates.');
        }

        if (!$this->checkPermission()) {
            return Reply::error('ACTION NOT ALLOWED.');
        }

        $lastVersionInfo = $this->getLastVersion($module);

        if ($lastVersionInfo['version'] <= $this->getCurrentVersion($module)) {
            return Reply::error('Your System IS ALREADY UPDATED to latest version !');
        }

        try {
            // Set up temporary backup directory
            $this->tmp_backup_dir = base_path() . '/backup_' . date('Ymd') . '_' . $module;

            // Get the update name and filename
            $update_name = $lastVersionInfo['archive'];
            $filename_tmp = config('froiden_envato.tmp_path') . '/' . $update_name;

            // Delete the old file if it exists
            if (file_exists($filename_tmp)) {
                File::delete($filename_tmp);
            }

            // Clear cache when the update button is clicked
            $this->configClear();


            // Backup the current version
            // $this->backup($module);

            // Return success response with download message
            return Reply::successWithData('Starting Download...', ['description' => $lastVersionInfo['description']]);
        } catch (\Exception $e) {
            // Handle update error and try to restore to the old status
            echo '<p>ERROR DURING UPDATE (!!check the update archive!!) --TRY to restore OLD status ........... </p>';

            $this->restore($module);
        }
    }

    public function install($module)
    {
        if (!$this->checkPermission()) {
            return Reply::error('ACTION NOT ALLOWED.');
        }

        $lastVersionInfo = $this->getLastVersion($module);

        $archive = $lastVersionInfo['archive'];
        $update_path = config('froiden_envato.tmp_path') . '/' . $archive;

        $zip = Zip::open($update_path);

        // $zip->extract(module_path($module));
        $zip->extract(base_path('Modules/'));
        // $this->unzipModule($zip, $module);
        // extract whole archive
        $this->clean();
        File::delete(public_path() . '/percent-module-download.txt');
        return Reply::success('Zip extracted successfully. Now installing...');
    }

    private function unzipModule($zip, $module)
    {
        $codeCanyonPath = storage_path('app') . '/Modules/Update';
        $zip->extract($codeCanyonPath);
        $files = File::allfiles($codeCanyonPath);

        foreach ($files as $file) {

            if (str_contains($file->getRelativePathname(), '.zip')) {
                $filePath = $file->getRelativePathname();
                $zip = Zip::open($codeCanyonPath . '/' . $filePath);
                $zip->extract(base_path('Modules/'));

                return true;
            }
        }

        $zip->extract(base_path('Modules/'));

        return true;
    }

    /*
    * Download Update from $update_baseurl to $tmp_path (local folder).
    */
    public function download($module)
    {

        if (!$this->checkPermission()) {
            return Reply::error('ACTION NOT ALLOWED.');
        }

        File::put(public_path() . '/percent-module-download.txt', '');

        $lastVersionInfo = $this->getLastVersion($module);

        $update_name = $lastVersionInfo['archive'];

        $filename_tmp = config('froiden_envato.tmp_path') . '/' . $update_name;

        $downloadRemoteUrl = config(strtolower($module) . '.update_baseurl') . '/' . $update_name;

        $dlHandler = fopen($filename_tmp, 'w');

        $client = new Client();
        $client->request('GET', $downloadRemoteUrl, [
            'sink' => $dlHandler,
            'progress' => function ($dl_total_size, $dl_size_so_far, $ul_total_size, $ul_size_so_far) {
                $percentDownloaded = ($dl_total_size > 0) ? (($dl_size_so_far / $dl_total_size) * 100) : 0;
                File::put(public_path() . '/percent-module-download.txt', $percentDownloaded);
            },
            'verify' => false
        ]);

        return Reply::success('Download complete. Now Installing...');

    }

    /*
    * Return current version (as plain text).
    */
    public function getCurrentVersion($module)
    {
        $version = File::get(module_path($module, 'version.txt'));
        return $version;
    }

    /*
    * Check if a new Update exist.
    */
    public function check($module)
    {
        $lastVersionInfo = $this->getLastVersion($module);

        if ($lastVersionInfo['version'] > $this->getCurrentVersion($module)) {
            return $lastVersionInfo['version'];
        }

        return '';
    }

    private function setCurrentVersion($last)
    {
        File::put(public_path() . '/version.txt', $last); // UPDATE $current_version to last version
    }

    private function getLastVersion($module)
    {
        $url = config(strtolower($module) . '.updater_file_path');

        return $this->getRemoteData($url);
    }

    private function backup($module)
    {
        $backup_dir = $this->tmp_backup_dir;

        // clear backup folder
        if (file_exists($backup_dir)) {
            File::deleteDirectory($backup_dir);
        }

        File::copyDirectory(module_path($module), $backup_dir); //to backup folder
    }

    private function restore($module)
    {
        if (!isset($this->tmp_backup_dir)) {
            $this->tmp_backup_dir = base_path() . '/backup_' . date('Ymd') . '_' . $module;
        }

        try {
            File::copyDirectory($this->tmp_backup_dir, module_path($module)); // to respective folder
        } catch (\Exception $e) {
            echo 'Exception => ' . $e->getMessage();
            echo '<br>[ FAILED ]';
            echo '<br> Backup folder is located in: <i>' . $this->tmp_backup_dir . '</i>.';
            echo '<br> Remember to restore System UP-Status through shell command: <i>php artisan up</i>.';

            return false;
        }

        echo '[ RESTORED ]';

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
        $percent = File::get(public_path() . '/percent-module-download.txt');

        return $percent;
    }

    public function checkIfFileExtracted($module)
    {
        $lastVersionInfo = $this->getLastVersion($module);

        if ($lastVersionInfo['version'] == $this->getCurrentVersion($module)) {

            $status = Artisan::call('migrate:check');

            if ($status) {
                Artisan::call('migrate', array('--force' => true)); // migrate database
            }

            $lastVersionInfo = $this->getLastVersion($module);
            $this->setCurrentVersion($lastVersionInfo['version']); // update system version

            // logout user after installing update
            $this->clean();

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

    private function getRemoteData($url)
    {
        $client = new Client();
        $res = $client->request('GET', $url, ['verify' => false]);
        $lastVersion = $res->getBody();

        $content = json_decode($lastVersion, true);

        return $content;

        if (!cache()->has($url)) {
            cache([$url => $content], now()->addMinutes(5));
        }

        return cache($url);
    }

}
