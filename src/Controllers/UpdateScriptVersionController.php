<?php

namespace Froiden\Envato\Controllers;

use Froiden\Envato\Helpers\Reply;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
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
        $setting = config('froiden_envato.setting');
        $this->appSetting = (new $setting)::first();
        if (Carbon::parse($this->appSetting->supported_until)->isPast()) {
            return Reply::error('Please renew your support for one-click updates.');
        }

        if (!$this->checkPermission()) {
            return Reply::error("ACTION NOT ALLOWED.");
        }

        $lastVersionInfo = $this->getLastVersion();

        if ($lastVersionInfo['version'] <= $this->getCurrentVersion()) {
            return Reply::error("Your System IS ALREADY UPDATED to latest version !");
        }

        try {
            $this->tmp_backup_dir = base_path() . '/backup_' . date('Ymd');

            $lastVersionInfo = $this->getLastVersion();

            $update_name = $lastVersionInfo['archive'];

            $filename_tmp = config('froiden_envato.tmp_path') . '/' . $update_name;


            if (file_exists($filename_tmp)) {
                File::delete($filename_tmp); //delete old file if exist
            }

            // Clear cache when update button is clicked
            $this->configClear();
            return Reply::successWithData('Starting Download...', ['description' => $lastVersionInfo['description']]);


            $status = $this->install($lastVersionInfo['version'], $update_path, $lastVersionInfo['archive']);

            if ($status) {

                echo '<p>&raquo; SYSTEM Mantence Mode => OFF</p>';
                echo '<p class="text-success">SYSTEM IS NOW UPDATED TO VERSION: ' . $lastVersionInfo['version'] . '</p>';
                echo '<p style="font-weight: bold;">RELOAD YOUR BROWSER TO SEE CHANGES</p>';
            } else
                throw new \Exception("Error during updating.");

        } catch (\Exception $e) {
            echo '<p>ERROR DURING UPDATE (!!check the update archive!!) --TRY to restore OLD status ........... ';

            $this->restore();

            echo '</p>';
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

        $lastVersionInfo = $this->getLastVersion();

        $update_name = $lastVersionInfo['archive'];

        $filename_tmp = config('froiden_envato.tmp_path') . '/' . $update_name;

        $downloadRemoteUrl = config('froiden_envato.update_baseurl') . '/' . $update_name;

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
        $client = new Client();
        $res = $client->request('GET', config('froiden_envato.updater_file_path'), ['verify' => false]);
        $lastVersion = $res->getBody();

        $content = json_decode($lastVersion, true);
        return $content; //['version' => $v, 'archive' => 'RELEASE-$v.zip', 'description' => 'plain text...'];
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
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
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
        $this->configClear();
        session()->forget('check_migrate_status');
        session()->flush();
        cache()->flush();
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
}
