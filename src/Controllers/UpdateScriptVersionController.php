<?php

namespace Froiden\Envato\Controllers;

use App\Http\Controllers\Controller;
use Froiden\Envato\Traits\UpdateVersion;
use Illuminate\Support\Facades\File;

class UpdateScriptVersionController extends Controller
{
    use UpdateVersion;

    public function __construct()
    {
        parent::__construct();
        // Get the setting and retrieve the app setting
        $setting = config('froiden_envato.setting');
        $this->appSetting = (new $setting)::first();
        $this->changePhpConfigs();
    }

    private function backup($filename)
    {
        $backup_dir = $this->tmp_backup_dir;

        if (!is_dir($backup_dir)) {
            File::makeDirectory($backup_dir, $mode = 0755, true, true);
        }

        if (!is_dir($backup_dir . '/' . dirname($filename))) {
            File::makeDirectory($backup_dir . '/' . dirname($filename), $mode = 0755, true, true);
        }

        File::copy(base_path() . '/' . $filename, $backup_dir . '/' . $filename); //To backup folder
    }

    private function restore()
    {
        if (!isset($this->tmp_backup_dir)) {
            $this->tmp_backup_dir = base_path() . '/backup_' . date('Ymd');
        }

        try {
            $backup_dir = $this->tmp_backup_dir;
            $backup_files = File::allFiles($backup_dir);

            foreach ($backup_files as $file) {
                $filename = (string)$file;
                $filename = substr($filename, (strlen($filename) - strlen($backup_dir) - 1) * (-1));
                echo $backup_dir . '/' . $filename . ' => ' . base_path() . '/' . $filename;
                File::copy($backup_dir . '/' . $filename, base_path() . '/' . $filename); // to respective folder
            }

        } catch (\Exception $e) {
            echo 'Exception => ' . $e->getMessage();
            echo '<BR>[ FAILED ]';
            echo '<BR> Backup folder is located in: <i>' . $backup_dir . '</i>.';
            echo '<BR> Remember to restore System UP-Status through shell command: <i>php artisan up</i>.';

            return false;
        }

        echo '[ RESTORED ]';

        return true;
    }

}
