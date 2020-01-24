<p align="center"><img src="https://froiden.com/img/orange-logo.svg" width="200px"></p>



## About Froiden Envato

Following changes to be made

- Keep the project in project-name folder (Example worksuite files in **worksuite** folder)

#### Outcome
- New version will be created in **versions** folder
- auto update will be created in **versions/auto-update/product-name** folder

## Installation

        composer require froiden/envato
    
## Publish files
        php artisan vendor:publish --provider="Froiden\Envato\FroidenEnvatoServiceProvider"
    
## Command to run for creating new version
        php artisan script:version {version}

