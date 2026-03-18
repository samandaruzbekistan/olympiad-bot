<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\BroadcastController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OlympiadController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth:admin')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::get('/subjects/create', [SubjectController::class, 'create'])->name('subjects.create');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::get('/subjects/{subject}/edit', [SubjectController::class, 'edit'])->name('subjects.edit');
        Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

        Route::get('/olympiads', [OlympiadController::class, 'index'])->name('olympiads.index');
        Route::get('/olympiads/create', [OlympiadController::class, 'create'])->name('olympiads.create');
        Route::post('/olympiads', [OlympiadController::class, 'store'])->name('olympiads.store');
        Route::get('/olympiads/{olympiad}/edit', [OlympiadController::class, 'edit'])->name('olympiads.edit');
        Route::put('/olympiads/{olympiad}', [OlympiadController::class, 'update'])->name('olympiads.update');
        Route::delete('/olympiads/{olympiad}', [OlympiadController::class, 'destroy'])->name('olympiads.destroy');

        Route::get('/registrations', [RegistrationController::class, 'index'])->name('registrations.index');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
        Route::get('/users/districts', [UserController::class, 'districts'])->name('users.districts');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');

        Route::get('/broadcast', [BroadcastController::class, 'create'])->name('broadcast.create');
        Route::post('/broadcast', [BroadcastController::class, 'send'])->name('broadcast.send');
    });
});
