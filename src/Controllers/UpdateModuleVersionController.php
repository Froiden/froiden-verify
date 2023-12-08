<?php

namespace Froiden\Envato\Controllers;

use App\Http\Controllers\Controller;
use Froiden\Envato\Traits\UpdateVersion;
use Illuminate\Support\Facades\File;

class UpdateModuleVersionController extends Controller
{
    use UpdateVersion;

    public function __construct()
    {
        parent::__construct();
        $this->changePhpConfigs();
        $this->appSetting = null;
        $module = request()->route('module');

        if ($module) {
            $this->moduleSetting($module);
        }



    }

    private function moduleSetting($module)
    {
        $settingInstance = config(strtolower($module) . '.setting');
        return $this->appSetting = $settingInstance::first();

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

}
