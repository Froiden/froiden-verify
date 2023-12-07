<?php

use Froiden\Envato\Controllers\PurchaseVerificationController;
use Froiden\Envato\Controllers\UpdateModuleVersionController;
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
        Route::get('update-version/update', [UpdateScriptVersionController::class, 'update'])->name('updateVersion.update');
        Route::get('update-version/download', [UpdateScriptVersionController::class, 'download'])->name('updateVersion.download');
        Route::get('update-version/downloadPercent', [UpdateScriptVersionController::class, 'downloadPercent'])->name('updateVersion.downloadPercent');
        Route::get('update-version/checkIfFileExtracted', [UpdateScriptVersionController::class, 'checkIfFileExtracted'])->name('updateVersion.checkIfFileExtracted');
        Route::get('update-version/install', [UpdateScriptVersionController::class, 'install'])->name('updateVersion.install');


        // For modules update
        Route::get('update-version/checkModuleSupport/{module}', [UpdateModuleVersionController::class, 'checkModuleSupport'])->name('updateVersion.checkModuleSupport');
        Route::get('update-version/updateModule/{module}', [UpdateModuleVersionController::class, 'update'])->name('updateVersion.updateModule');
        Route::get('update-version/downloadModule/{module}', [UpdateModuleVersionController::class, 'download'])->name('updateVersion.downloadModule');
        Route::get('update-version/downloadPercentModule/{module}', [UpdateModuleVersionController::class, 'downloadPercent'])->name('updateVersion.downloadPercentModule');
        Route::get('update-version/checkIfFileExtractedModule/{module}', [UpdateModuleVersionController::class, 'checkIfFileExtracted'])->name('updateVersion.checkIfFileExtractedModule');
        Route::get('update-version/installModule/{module}', [UpdateModuleVersionController::class, 'install'])->name('updateVersion.installModule');
        Route::get('update-version/refreshModule/{module}', [UpdateModuleVersionController::class, 'refresh'])->name('updateVersion.refreshModule');
    });
});
