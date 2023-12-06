<?php

use Froiden\Envato\Controllers\PurchaseVerificationController;
use Froiden\Envato\Controllers\UpdateScriptVersionController;

Route::group(['middleware' => 'web'], function () {

    Route::get('verify-purchase', [PurchaseVerificationController::class, 'verifyPurchase'])->name('verify-purchase');
    Route::post('purchase-verified', [PurchaseVerificationController::class, 'purchaseVerified'])->name('purchase-verified');
    Route::get('update-database', [UpdateScriptVersionController::class, 'updateDatabase'])->name('update-database');
    Route::get('clear-cache', [UpdateScriptVersionController::class, 'clearCache']);
    Route::get('refresh-cache', [UpdateScriptVersionController::class, 'refreshCache']);
    Route::get('down/{hash}', [UpdateScriptVersionController::class, 'down']);
    Route::get('up/{hash}', [UpdateScriptVersionController::class, 'up']);
    Route::get('hide-review-modal/{type}', [PurchaseVerificationController::class, 'hideReviewModal'])->name('hide-review-modal');


//    Route::post('purchase-verified', ['uses' => 'PurchaseVerificationController@purchaseVerified'])->name('purchase-verified');
//    Route::get('update-database', ['uses' => 'UpdateScriptVersionController@updateDatabase'])->name('update-database');

//    Route::get('clear-cache', ['uses' => 'UpdateScriptVersionController@clearCache']);
//    Route::get('refresh-cache', ['uses' => 'UpdateScriptVersionController@refreshCache']);

//    Route::get('down/{hash}', ['uses' => 'UpdateScriptVersionController@down']);
//    Route::get('up/{up}', ['uses' => 'UpdateScriptVersionController@up']);

    // Hide Review Modal
//    Route::get('hide-review-modal/{type}', ['uses' => 'PurchaseVerificationController@hideReviewModal'])->name('hide-review-modal');

    // update script version
    Route::group(['as' => 'admin.','prefix' => 'admin'], function () {
        Route::get('update-version/update', [UpdateScriptVersionController::class, 'update'])->name('updateVersion.update');
        Route::get('update-version/download', [UpdateScriptVersionController::class, 'download'])->name('updateVersion.download');
        Route::get('update-version/downloadPercent', [UpdateScriptVersionController::class, 'downloadPercent'])->name('updateVersion.downloadPercent');
        Route::get('update-version/checkIfFileExtracted', [UpdateScriptVersionController::class, 'checkIfFileExtracted'])->name('updateVersion.checkIfFileExtracted');
        Route::get('update-version/install', [UpdateScriptVersionController::class, 'install'])->name('updateVersion.install');

//        Route::get('update-version/update', ['as' => 'updateVersion.update', 'uses' => 'UpdateScriptVersionController@update']);
//        Route::get('update-version/download', ['as' => 'updateVersion.download', 'uses' => 'UpdateScriptVersionController@download']);
//        Route::get('update-version/downloadPercent', ['as' => 'updateVersion.downloadPercent', 'uses' => 'UpdateScriptVersionController@downloadPercent']);
//        Route::get('update-version/checkIfFileExtracted', ['as' => 'updateVersion.checkIfFileExtracted', 'uses' => 'UpdateScriptVersionController@checkIfFileExtracted']);
//        Route::get('update-version/install', ['as' => 'updateVersion.install', 'uses' => 'UpdateScriptVersionController@install']);
    });
    // For old routes on worksuite-saas
    Route::get('super-admin/update-version/checkIfFileExtracted', [UpdateScriptVersionController::class, 'checkIfFileExtracted']);
//    Route::get('super-admin/update-version/checkIfFileExtracted', ['uses' => 'UpdateScriptVersionController@checkIfFileExtracted']);
});
