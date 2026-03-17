<?php

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/payment/click', [PaymentController::class, 'handleClick']);
