<?php

namespace Froiden\Envato\Traits;

use App\Helper\Reply;
use Illuminate\Support\Facades\Artisan;

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

}
