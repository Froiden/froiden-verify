<?php

use Froiden\Envato\Controllers\PurchaseVerificationController;
use Froiden\Envato\Controllers\UpdateScriptVersionController;

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
        Route::get('update-version/update', [UpdateScriptVersionController::class, 'update'])->name('updateVersion.update');
        Route::get('update-version/download', [UpdateScriptVersionController::class, 'download'])->name('updateVersion.download');
        Route::get('update-version/downloadPercent', [UpdateScriptVersionController::class, 'downloadPercent'])->name('updateVersion.downloadPercent');
        Route::get('update-version/checkIfFileExtracted', [UpdateScriptVersionController::class, 'checkIfFileExtracted'])->name('updateVersion.checkIfFileExtracted');
        Route::get('update-version/install', [UpdateScriptVersionController::class, 'install'])->name('updateVersion.install');

    });
});
