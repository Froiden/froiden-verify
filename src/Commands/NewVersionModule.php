<?php

namespace Froiden\Envato\Commands;

use Illuminate\Console\Command;
use File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class NewVersionModule extends Command
{

    private $product;
    private $module;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:version {version} {module}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to version the script';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->product = config('froiden_envato.envato_product_name');

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $version = $this->argument('version');
        $this->module = $this->argument('module');

        $path = $this->createVersionZip($version);

        $this->comment("\n" . $this->module . '-' . $version.' and '. $this->module . '-auto-' . $version.' is Ready to distribute');

        $this->comment("\n" . 'run ./upload.sh to upload to froiden server');

    }

    private function createVersionZip($version)
    {

        $folder = $this->product.'-'.strtolower($this->module) . '-module-' . $version;
        $versionFolder = '../versions/';
        $path = $versionFolder . $folder . '/'.$this->module;
        $local = '../' . $this->product.'/Modules/'.$this->module . '/';
        $this->comment($local);
        $this->comment("\n\n" . '------Creating Versions------');
        $this->info(' Removing Old ' . $folder . ' folder to create the new');
        echo exec('rm -rf '.$versionFolder . $folder);
        echo exec('rm -rf '.$versionFolder . $folder.'.zip');

        $this->info(' Creating the directory ' . $folder . '/'.$this->module);
        echo exec('mkdir -p ' . $path);


        $this->info(' Copying files from ' . $local . ' ' . $path);
        echo exec('rsync -av --progress ' . $local . ' ' . $path . ' --exclude=".git" --exclude=".phpintel" --exclude=".env" --exclude=".idea"');


        $this->info(' removing old version.txt file');
        echo exec('rm ' . $local . '/version.txt');
        $this->info(' Copying version to know the version to version.txt file');
        echo exec('echo ' . $version . '>> ' . $local . 'version.txt');


        $this->info(' Removing laraupdater and upload.sh file');
        echo exec('rm -rf ' . $path . '/upload.sh');

        $this->info(' Removing old version.txt file');
        echo exec('rm ' . $path . '/version.txt');

        $this->info(' Copying '.$version.' version to know the version to version.txt file');
        echo exec('echo ' . $version . '>> ' . $path . '/version.txt');

        $this->info(' Moving '.$this->module.'/documentation to separate folder');
        echo exec('mv ' . $path . '/Documentation ' . $path.'/../documentation/');
        $this->info('cd '.$versionFolder . $folder.'; zip -r ' . $this->module . '.zip '.$this->module);

        // Creating of module
        echo exec('cd '.$versionFolder . $folder.'; zip -r ' . $this->module . '.zip '.$this->module);


        echo exec('rm -rf ' . $path);

        // Zipping the folder
        $this->info(' Zipping the folder');
        echo exec('cd ../versions; zip -r ' . $folder . '.zip ' . $folder . '/');

        return $path;
    }


}
