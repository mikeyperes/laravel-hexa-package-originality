<?php

use hexa_package_originality\Http\Controllers\OriginalityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('originality/settings', [OriginalityController::class, 'settings'])->name('originality.settings');
    Route::post('originality/settings', [OriginalityController::class, 'saveSettings'])->name('originality.settings.save');
    Route::post('originality/test', [OriginalityController::class, 'testConnection'])->name('originality.test');
    Route::get('raw-originality', [OriginalityController::class, 'raw'])->name('originality.raw');
    Route::post('originality/detect', [OriginalityController::class, 'detect'])->name('originality.detect');
});
