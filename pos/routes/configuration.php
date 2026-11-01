<?php

use Illuminate\Support\Facades\Route;
use Pos\Http\ConfigurationAccess;
use Pos\Http\ConfigurationController;

Route::middleware(['pos.staff', ConfigurationAccess::class])->group(function () {
    Route::get('/config', [ConfigurationController::class, 'show']);
    Route::post('/config/{section}', [ConfigurationController::class, 'save'])->middleware('throttle:5,1');
});
