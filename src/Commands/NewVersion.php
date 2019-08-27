<?php

namespace Froiden\Envato\Commands;

use Illuminate\Console\Command;
use File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class NewVersion extends Command
{

    private $product = '';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'script:version {version}';


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

        $this->clean();

        $path = $this->createVersionZip($version);

        $this->createAutoUpdate($version);

        // Grab Filename and path
        $filePath = $path . '.zip';
        $array = explode('/', $filePath);
        $fileName = end($array);

        if ($this->confirm('Do you wish ' . $filePath . ' to CODECANYON server?', 'yes')) {
            if (config('filesystems.customFtp.host') == '') {
                $this->error('Please create the variables FTP_HOST, FTP_USERNAME, FTP_PASSWORD in .env file to process it');
                return false;
            }
            $this->uploadToCodecanyon($filePath, $fileName);
        }
        $this->info("\n" . 'Uploaded successfully to CODECANYON server');
        $this->comment("\n" . 'run ./upload.sh to upload to froiden server');

    }

    private function createVersionZip($version)
    {

        $folder = $this->product . '-' . $version;
        $path = '../versions/' . $folder . '/script';
        $local = '../' . $this->product . '/';

        $this->comment("\n\n" . '------Creating Versions------');
        $this->info(' Removing Old ' . $folder . ' folder to create the new');
        echo exec('rm -rf ' . $folder);

        $this->info(' Creating the directory ' . $folder . '/script');
        echo exec('mkdir -p ' . $path);

        $this->info(' removing old version.txt file');
        echo exec('rm ' . $local . '/public/version.txt');

        $this->info(' Copying version to know the version to version.txt file');
        echo exec('echo ' . $version . '>> ' . $local . 'public/version.txt');


        $this->info(' Copying files from ' . $local . ' ' . $path);
        echo exec('rsync -av --progress ' . $local . ' ' . $path . ' --exclude=".git" --exclude=".phpintel" --exclude=".env" --exclude=".idea"');


        $this->info(' Removing installed');
        echo exec('rm -rf ' . $path . '/storage/installed');


        $this->info(' Removing legal file');
        echo exec('rm -rf ' . $path . 'storage/legal');


        $this->info(' Delete Storage and public uploads Folder Files');
        echo exec('rm -rf ' . $path . '/public/storage');
        echo exec('rm -rf ' . $path . '/public/user-uploads/*');

        $this->info(' Removing Zip files');
        echo exec('rm -rf ' . $path . '/storage/app/*.zip');

        $this->info(' Removing symlink');
        echo exec('find ' . $path . '/storage/app/public \! -name ".gitignore" -delete');


        $this->info(' Copying .env.example to .env');
        echo exec('cp ' . $path . '/.env.example ' . $path . '/.env');

        $this->info(' Delete log files');
        echo exec('rm ' . $path . '/storage/logs/*.log');

        $this->info(' Removing laraupdater and upload.sh file');
        echo exec('rm -rf ' . $path . '/laraupdater.json');
        echo exec('rm -rf ' . $path . '/upload.sh');


        $this->info(' removing old version.txt file');
        echo exec('rm ' . $path . '/public/version.txt');


        $this->info(' Copying version to know the version to version.txt file');
        echo exec('echo ' . $version . '>> ' . $path . '/public/version.txt');


        $this->info(' Moving script/documentation to separate folder');
        echo exec('mv ' . $path . '/documentation ' . $path.'/../documentation/');

        // Zipping the folder

        $this->info(' Zipping the folder');
        echo exec('cd ../versions; zip -r ' . $folder . '.zip ' . $folder . '/');

        return $path;
    }

    private function createAutoUpdate($version)
    {
        //start quick update version
        $this->output->progressStart(8);
        $folder = $this->product . '-auto-' . $version;
        $path = '../versions/auto-update/' . $this->product;
        $local = '../' . $this->product . '/';


        $this->comment("\n\n\n" . '------Creating Auto update version------');
        $this->info(' Removing Old ' . $folder . ' folder to create the new');
        echo exec('rm -rf ' . $path);
        echo exec('mkdir -p ' . $path);

        $this->info(' Copying files from ' . $local . ' to ' . $path);
        echo exec('rsync -av --progress ' . $local . ' ' . $path . ' --exclude=".git" --exclude=".phpintel" --exclude=".env" --exclude="public/.htaccess" --exclude="public/favicon" --exclude="public/favicon.ico" --exclude=".gitignore" --exclude=".idea"');

        $this->info(' Delete Storage and public uploads Folder Files');
        echo exec('rm -rf ' . $path . '/public/storage');
        echo exec('rm -rf ' . $path . '/public/user-uploads/*');

        $this->info(' Delete Language Folder Files');
        echo exec('rm -rf ' . $path . '/resources/lang/*');

        $this->info(' Creating the en directory ' . $path . '/resources/lang');
        echo exec('mkdir -p ' . $path . '/resources/lang/en');

        $this->info(' Copy English Language Folder Files');
        echo exec('cp ' . $local . 'resources/lang/en/* ' . $path . '/resources/lang/en/');

        $this->info(' Removing symlink');
        echo exec('find ' . $path . '/storage/app/public \! -name ".gitignore" -delete');

        $this->info(' Delete log files');
        echo exec('rm ' . $path . '/storage/logs/*.log');

        $this->info(' Removing Zip files');
        echo exec('rm -rf ' . $path . '/storage/app/*.zip');

        $this->info(' Removing Documentation folder');
        echo exec('rm -rf ' . $path . '/documentation');

        $this->info(' Removing laraupdater and upload.sh file');
        echo exec('rm -rf ' . $path . '/laraupdater.json');
        echo exec('rm -rf ' . $path . '/upload.sh');

        $this->info(' removing old version.txt file');
        echo exec('rm ' . $path . '/public/version.txt');

        $this->info(' Copying version to know the version to version.txt file');
        echo exec('echo ' . $version . '>> ' . $path . '/public/version.txt');

        return $path;
    }

    private function removeFiles()
    {

    }

    private function uploadToCodecanyon($filePath, $fileName)
    {
        $this->comment('------Uploading to server------');
        $localFile = File::get($filePath);

        Storage::disk('customFtp')->put($fileName, $localFile);
        $this->info('Done....');
    }

    private function clean()
    {
        $this->comment("\n" . '------Cleaning------');
        $this->info(' php artisan debugbar:clear');
        try {
            Artisan::call('debugbar:clear');
        } catch (\Exception $exception) {
            $this->info(' Debugbar not present');
        }

        $this->info(' php artisan vendor:cleanup');
        Artisan::call('vendor:cleanup');

        $this->info(' php artisan cache:clear');
        Artisan::call('cache:clear');

        $this->info(' php artisan view:clear');
        Artisan::call('view:clear');

        $this->info(' php artisan config:clear');
        Artisan::call('config:clear');
    }

}
