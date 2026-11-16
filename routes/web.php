<?php

use App\Http\Controllers\Auth\ForcedPasswordChangeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Frontend\UserController as FrontendUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TwoFactorController;
use App\Livewire\FleetDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'loginIndex'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post')->middleware('throttle:login');
Route::get('/', FleetDashboard::class)->name('frontend.index');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware(['auth'])->group(function () {
    Route::get('/2fa/setup/{user?}', [TwoFactorController::class, 'setup'])->name('2fa.setup');
    Route::get('/2fa/disable/{user?}', [TwoFactorController::class, 'showDisableForm'])->name('2fa.disable.form');
    Route::post('/2fa/enable', [TwoFactorController::class, 'enable'])->name('2fa.enable');
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');
});

Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::post('/2fa', function () {
        return redirect()->intended('/home');
    })->name('2fa');
});

// Root routes with all users access, with OTP
Route::middleware(['auth', '2fa', 'force.password.change'])->group(function () {
    Route::get('profile/edit', [FrontendUserController::class, 'edit'])->name('xpress.profile.edit');
    Route::put('profile', [FrontendUserController::class, 'updateProfile'])->name('xpress.profile.update');
    Route::get('change-password', [FrontendUserController::class, 'changePasswordIndex'])->name('xpress.change-password-index');
    Route::put('change-password', [FrontendUserController::class, 'changePassword'])->name('xpress.change-password');

    Route::get('/force-change-password', [ForcedPasswordChangeController::class, 'edit'])->name('password.force.edit');
    Route::put('/force-change-password', [ForcedPasswordChangeController::class, 'update'])->name('password.force.update');
});
