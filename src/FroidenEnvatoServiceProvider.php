<?php

namespace Froiden\Envato;

use Froiden\Envato\Commands\NewVersion;
use Froiden\Envato\Commands\VendorCleanUpCommand;
use Illuminate\Support\ServiceProvider;

class FroidenEnvatoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/Config/froiden_envato.php','froiden_envato'
        );
        $this->commands([
            VendorCleanUpCommand::class,
            NewVersion::class
        ]);
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot()
    {
        $this->publishFiles();

        include __DIR__ . '/routes.php';
    }

    public function publishFiles()
    {
        $this->publishes([
            __DIR__ . '/Config/froiden_envato.php' => config_path('froiden_envato.php'),
        ]);

        $this->publishes([
            __DIR__ . '/Migrations/' => database_path('migrations')
        ], 'migrations');

        $this->publishes([
            __DIR__ . '/Views' => resource_path('views/vendor/froiden-envato'),
        ]);

    }
}
