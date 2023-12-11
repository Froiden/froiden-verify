<?php

use Froiden\Envato\Controllers\PurchaseVerificationController;
use Froiden\Envato\Controllers\UpdateScriptVersionController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'web'], function () {

    Route::get('verify-purchase', [PurchaseVerificationController::class, 'verifyPurchase'])->name('verify-purchase');
    Route::post('purchase-verified', [PurchaseVerificationController::class, 'purchaseVerified'])->name('purchase-verified');
    Route::get('hide-review-modal/{type}', [PurchaseVerificationController::class, 'hideReviewModal'])->name('hide-review-modal');

    Route::get('update-database', [UpdateScriptVersionController::class, 'updateDatabase'])->name('update-database');
    Route::get('clear-cache', [UpdateScriptVersionController::class, 'clearCache']);
    Route::get('refresh-cache', [UpdateScriptVersionController::class, 'refreshCache']);
    Route::get('down/{hash}', [UpdateScriptVersionController::class, 'down']);
    Route::get('up/{hash}', [UpdateScriptVersionController::class, 'up']);

    // Update script version routes
    Route::group(['as' => 'admin.', 'prefix' => 'admin'], function () {
        Route::get('update-version/update/{module?}', [UpdateScriptVersionController::class, 'update'])->name('updateVersion.update');
        Route::get('update-version/download/{module?}', [UpdateScriptVersionController::class, 'download'])->name('updateVersion.download');
        Route::get('update-version/downloadPercent/{module?}', [UpdateScriptVersionController::class, 'downloadPercent'])->name('updateVersion.downloadPercent');
        Route::get('update-version/checkIfFileExtracted/{module?}', [UpdateScriptVersionController::class, 'checkIfFileExtracted'])->name('updateVersion.checkIfFileExtracted');
        Route::get('update-version/install/{module?}', [UpdateScriptVersionController::class, 'install'])->name('updateVersion.install');
        Route::get('update-version/checkSupport/{module?}', [UpdateScriptVersionController::class, 'checkSupport'])->name('updateVersion.checkSupport');
        Route::get('update-version/refresh/{module?}', [UpdateScriptVersionController::class, 'refresh'])->name('updateVersion.refresh');
        Route::post('update-version/notify/{module}', [UpdateScriptVersionController::class, 'notify'])->name('updateVersion.notify');
    });
});
