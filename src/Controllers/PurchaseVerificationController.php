<?php

namespace Froiden\Envato\Controllers;

use Illuminate\Routing\Controller;
use Froiden\Envato\Traits\AppBoot;
use Nwidart\Modules\Facades\Module as ModulesModule;
use Illuminate\Support\Facades\File;

class PurchaseVerificationController extends Controller
{
    use AppBoot;

    public function checkEnv()
    {
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

        return [
            'app' => config('froiden_envato.envato_product_name'),
            'redirect_https' => config('app.redirect_https'),
            'version' => trim(File::get('version.txt')),
            'debug' => config('app.debug'),
            'queue' => config('queue.default'),
            'php' => phpversion(),
            'email' => global_setting()->email,
            'purchase_info' => global_setting()->purchase_code ? [
                'purchase_code' => global_setting()->purchase_code
                    ? substr(global_setting()->purchase_code, 0, -5) . str_repeat('*', 5)
                    : null,
                'supported_until' => global_setting()->supported_until ? global_setting()->supported_until->format('Y-m-d H:i A') : null,
                'last_license_verified_at' => global_setting()->last_license_verified_at ? global_setting()->last_license_verified_at->format('Y-m-d H:i A') : null,
            ] : null,
            'environment' => app()->environment(),
            'smtp_verified' => $smtpVerified,
            'all_modules' => $updateArray,
            'modules_enabled' => $updateArrayEnabled,
        ];
    }
}
