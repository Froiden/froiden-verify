<?php

namespace Froiden\Envato\Controllers;

use Illuminate\Routing\Controller;

use Nwidart\Modules\Facades\Module as ModulesModule;
use Illuminate\Support\Facades\File;

class CheckController extends Controller
{

    private $appSetting;

    public function check()
    {
        $setting = config('froiden_envato.setting');
        $this->appSetting = (new $setting)::first();

        $plugins = ModulesModule::all(); /* @phpstan-ignore-line */
        $updateArray = [];
        $updateArrayEnabled = [];

        foreach ($plugins as $key => $plugin) {
            $modulePath = $plugin->getPath();
            $version = trim(File::get($modulePath . '/version.txt'));

            if ($plugin->isEnabled()) {
                $updateArrayEnabled[$key] = $version;
            }

            $updateArray[$key] = $version;
        }

        $smtpVerified = smtp_setting()->verified;

        $userEmail = class_exists(\App\Models\User::class) ? \App\Models\User::first()->email : null;

        return [
            'app' => config('froiden_envato.envato_product_name'),
            'redirect_https' => config('app.redirect_https'),
            'version' => trim(File::get('version.txt')),
            'debug' => config('app.debug'),
            'queue' => config('queue.default'),
            'php' => phpversion(),
            'email' => $userEmail,
            'purchase_info' => $this->appSetting->purchase_code ? [
                'purchase_code' => $this->appSetting->purchase_code
                    ? substr($this->appSetting->purchase_code, 0, -5) . str_repeat('*', 5)
                    : null,
                'supported_until' => $this->appSetting->supported_until ? $this->appSetting->supported_until->format('Y-m-d H:i A') : null,
                'last_license_verified_at' => $this->appSetting->last_license_verified_at ? $this->appSetting->last_license_verified_at->format('Y-m-d H:i A') : null,
            ] : null,
            'environment' => app()->environment(),
            'smtp_verified' => $smtpVerified,
            'all_modules' => $updateArray,
            'modules_enabled' => $updateArrayEnabled,
        ];
    }
}
