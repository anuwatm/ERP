<?php

use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

Route::post('/gateway/{config}/settlement', [GatewayController::class, 'webhook'])->middleware('throttle:60,1')->name('gateway.webhook');
