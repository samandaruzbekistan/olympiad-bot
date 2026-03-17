<?php

use App\Http\Controllers\Api\PaymeController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/payment/click', [PaymentController::class, 'handleClick']);
Route::post('/payment/payme', [PaymeController::class, 'handle']);
