<?php

use App\Http\Controllers\Api\ETaxWebhookController;
use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

Route::post('/gateway/{config}/settlement', [GatewayController::class, 'webhook'])->middleware('throttle:60,1')->name('gateway.webhook');
Route::post('/gateway/{config}/opn', [GatewayController::class, 'opn'])->middleware('throttle:30,1')->name('gateway.opn');

Route::post('/e-tax/{config}/callback', [ETaxWebhookController::class, 'handle'])->middleware('throttle:60,1')->name('etax.webhook');
Route::post('/e-tax/callback', [ETaxWebhookController::class, 'handleDefault'])->middleware('throttle:60,1')->name('etax.webhook.default');
