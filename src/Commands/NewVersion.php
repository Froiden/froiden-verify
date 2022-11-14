<?php

namespace Froiden\Envato\Commands;

use Illuminate\Console\Command;
use File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class NewVersion extends Command
{

    private $product;
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


        $this->comment("\n" . $this->product . '-' . $version.' and '. $this->product . '-auto-' . $version.' is Ready to distribute');

        $this->comment("\n" . 'run ./upload.sh to upload to froiden server');


    }

    private function createVersionZip($version)
    {

        $folder = $this->product . '-' . $version;
        $versionFolder = '../versions/';
        $path = $versionFolder . $folder . '/script';
        $local = '../' . $this->product . '/';

        $this->comment("\n\n" . '------Creating Versions------');
        $this->info(' Removing Old ' . $folder . ' folder to create the new');
        echo exec('rm -rf '.$versionFolder . $folder);
        echo exec('rm -rf '.$versionFolder . $folder.'.zip');

        $this->info(' Creating the directory ' . $folder . '/script');
        echo exec('mkdir -p ' . $path);


        $this->info(' Copying files from ' . $local . ' ' . $path);
        echo exec('rsync -av --progress ' . $local . ' ' . $path . ' --exclude=".git" --exclude=".phpintel" --exclude=".env" --exclude=".idea"');


        $this->info(' Removing installed');
        echo exec('rm -rf ' . $path . '/storage/installed');
               
        $this->info(' Removing Legal and Reviewed file');
        echo exec('rm -rf ' . $path . 'storage/legal');
        echo exec('rm -rf ' . $path . 'storage/reviewed');
        
        
        $this->info(' Removing .gitlab folder');
        echo exec('rm -rf ' . $path . '.gitlab');


        $this->info(' Delete Storage Folder Files');
        echo exec('rm -rf ' . $path . '/public/storage');

        if ($this->product == 'recruit-saas') {
            $this->comment(' Removing user-uploads folders except front-features/feature-* folder for codecanyon zip version');
            $this->deleteDir($path . '/public/user-uploads');

        } else {
            $this->info(' Removing user-uploads folders');
            echo exec('rm -rf ' . $path . '/public/user-uploads/*');
        }

        $this->info(' removing old version.txt file');
        echo exec('rm ' . $local . '/public/version.txt');
        $this->info(' Copying version to know the version to version.txt file');
        echo exec('echo ' . $version . '>> ' . $local . 'public/version.txt');

        $this->info(' Removing auto-update zip files from storage folder');
        echo exec('rm -rf ' . $path . '/storage/app/*.zip');

        $this->info(' Removing modules_status.json');
        echo exec('rm -rf ' . $path . '/storage/app/modules_statuses.json');

        $this->info(' Removing symlink');
        echo exec('find ' . $path . '/storage/app/public \! -name ".gitignore" -delete');


        $this->info(' Copying .env.example to .env');
        echo exec('cp ' . $path . '/.env.example ' . $path . '/.env');
        
        $this->info(' Copying .htaccess of user-uploads to user-uploads');
        echo exec('cp ' . $path . '/public/user-uploads/.htaccess ' . $path . '/public/user-uploads/.htaccess');
        echo exec('chmod -R 755 ' . $path . '/public/user-uploads/');

        $this->info(' Delete log files');
        echo exec('rm ' . $path . '/storage/logs/*.log');

        $this->info(' Delete down files');
        echo exec('rm ' . $path . '/storage/framework/down');
        echo exec('rm ' . $path . '/storage/framework/maintenance.php');

        $this->info(' Removing laraupdater and upload.sh file');
        echo exec('rm -rf ' . $path . '/laraupdater.json');
        echo exec('rm -rf ' . $path . '/upload.sh');
        $this->info(' gitlab_Ci');
        echo exec('rm -rf ' . $path . '/.gitlab-ci.yml');

        $this->info(' Removing old version.txt file');
        echo exec('rm ' . $path . '/public/version.txt');
        
        $this->info(' Removing node_modules folder');
        echo exec('rm -rf ' . $path . '/node_modules');

        $this->info(' Copying '.$version.' version to know the version to version.txt file');
        echo exec('echo ' . $version . '>> ' . $path . '/public/version.txt');

        $this->info(' Moving script/documentation to separate folder');
        echo exec('mv ' . $path . '/documentation ' . $path.'/../documentation/');


        $this->comment("\n\n" . '------Emptying Modules Directroy------'."\n\n");
        echo exec('rm -rf ' . $path . '/Modules/*');

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
        
        $this->info(' Removing .gitlab folder');
        echo exec('rm -rf ' . $path . '.gitlab');

        $this->info(' Delete Language Folder Files');
        echo exec('rm -rf ' . $path . '/resources/lang/*');

        $this->info(' Creating the en directory ' . $path . '/resources/lang');
        echo exec('mkdir -p ' . $path . '/resources/lang/en');

        $this->info(' Copy English Language Folder Files');
        echo exec('cp ' . $local . 'resources/lang/en/* ' . $path . '/resources/lang/en/');
        
        $this->info(' Copying .htaccess of user-uploads to user-uploads');
        echo exec('cp ' . $path . '/public/user-uploads/.htaccess ' . $path . '/public/user-uploads/.htaccess');
        echo exec('chmod -R 755 ' . $path . '/public/user-uploads/');

        $this->info(' Removing symlink');
        echo exec('find ' . $path . '/storage/app/public \! -name ".gitignore" -delete');

        $this->info(' Delete log files');
        echo exec('rm ' . $path . '/storage/logs/*.log');

        $this->info(' Delete down files');
        echo exec('rm ' . $path . '/storage/framework/down');
        echo exec('rm ' . $path . '/storage/framework/maintenance.php');

        $this->info(' Removing modules_status.json');
        echo exec('rm -rf ' . $path . '/storage/app/modules_statuses.json');

        $this->info(' Removing Zip files');
        echo exec('rm -rf ' . $path . '/storage/app/*.zip');
        
        $this->info(' Removing Legal and Reviewed file');
        echo exec('rm -rf ' . $path . 'storage/legal');
        echo exec('rm -rf ' . $path . 'storage/reviewed');
        

        $this->info(' Removing Documentation folder');
        echo exec('rm -rf ' . $path . '/documentation');

        $this->info(' Removing node_modules folder');
        echo exec('rm -rf ' . $path . '/node_modules');

        $this->info(' Removing laraupdater and upload.sh file');
        echo exec('rm -rf ' . $path . '/laraupdater.json');
        echo exec('rm -rf ' . $path . '/upload.sh');
        
        $this->info(' gitlab_Ci');
        echo exec('rm -rf ' . $path . '/.gitlab-ci.yml');

        $this->info(' removing old version.txt file');
        echo exec('rm ' . $path . '/public/version.txt');

        $this->info(' Copying version to know the version to version.txt file');
        echo exec('echo ' . $version . '>> ' . $path . '/public/version.txt');

        $this->comment("\n\n" . '------Emptying Modules Directroy------'."\n\n");
        echo exec('rm -rf ' . $path . '/Modules/*');

        return $path;
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
        
        $this->info('php artisan up');
        Artisan::call('up');
        
    }

    private function deleteDir($dirPath)
    {

        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }

        $files = glob($dirPath . '*', GLOB_MARK);

        foreach ($files as $file) {
            echo $file . "\n";

            if (is_dir($file)) {
                // This is required from recruit-saas
                if ($file == 'front-features') continue;
                echo exec('rm -rf ' . $file);
                self::deleteDir($file);

            } else {
                if (strpos($file, 'feature-') !== false || strpos($file, '.gitignore') !== false) {
                    continue;
                }

                unlink($file);
            }
        }
    }

}
