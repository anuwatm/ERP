<?php

use App\Http\Controllers\Api\SummaryController;
use App\Http\Middleware\AuthenticateIntegrationKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/summaries')->name('api.v1.summaries.')->group(function () {
    Route::get('/projects', [SummaryController::class, 'projects'])->middleware(AuthenticateIntegrationKey::class.':summary:projects:read')->name('projects');
    Route::get('/sales', [SummaryController::class, 'sales'])->middleware(AuthenticateIntegrationKey::class.':summary:sales:read')->name('sales');
    Route::get('/finance', [SummaryController::class, 'finance'])->middleware(AuthenticateIntegrationKey::class.':summary:finance:read')->name('finance');
});
