<?php

use App\Http\Controllers\Api\ClickController;
use App\Http\Controllers\Api\PaymeController;
use Illuminate\Support\Facades\Route;

Route::post('/click', [ClickController::class, 'handle']);
Route::post('/payment/payme', [PaymeController::class, 'handle']);
