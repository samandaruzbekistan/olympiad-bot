<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OlympiadController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth:admin')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::get('/subjects/create', [SubjectController::class, 'create'])->name('subjects.create');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::get('/olympiads', [OlympiadController::class, 'index'])->name('olympiads.index');
        Route::get('/olympiads/create', [OlympiadController::class, 'create'])->name('olympiads.create');
        Route::post('/olympiads', [OlympiadController::class, 'store'])->name('olympiads.store');
        Route::get('/registrations', [RegistrationController::class, 'index'])->name('registrations.index');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    });
});
