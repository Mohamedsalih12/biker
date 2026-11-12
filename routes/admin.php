<?php

use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\UserPasswordResetController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Frontend\UserController as FrontendUserController;
use Illuminate\Support\Facades\Route;

// Admin routes with admin and director access, with OTP
Route::middleware(['auth', '2fa', 'force.password.change'])->group(function () {

    Route::get('settings', [SettingController::class, 'index'])->name('settings');
    Route::post('settings', [SettingController::class, 'store'])->name('settings.store');

    Route::controller(AdminUserController::class)->middleware('can:manage-users')->group(function () {
        Route::get('users', 'index')->name('xpress.users.index');
        Route::get('users/userdetails', 'userdetails')->name('xpress.users.userdetails');
        Route::get('users/create', 'create')->name('xpress.users.create');
        Route::post('users', 'store')->name('xpress.users.store');
        Route::get('users/{user}/edit', 'edit')->name('xpress.users.edit');
        Route::put('users/{user}', 'update')->name('xpress.users.update');
        Route::delete('users/{user}/delete', 'destroy')->name('xpress.users.destroy');
    });

    Route::controller(FrontendUserController::class)->middleware('can:manage-users')->group(function () {
        Route::get('profile/{user}/edit', 'editProfileAdmin')->name('xpress.adminprofile.edit');
        Route::put('profile/{user}', 'updateProfileAdmin')->name('xpress.adminprofile.update');
    });

    Route::controller(VehicleController::class)->middleware('can:manage-vehicles')->group(function () {
        Route::get('vehicles', 'index')->name('xpress.vehicles.index');
        Route::get('vehicles/create', 'create')->name('xpress.vehicles.create');
        Route::post('vehicles', 'store')->name('xpress.vehicles.store');
        Route::get('vehicles/{vehicle}', 'show')->name('xpress.vehicles.show');
        Route::get('vehicles/{vehicle}/edit', 'edit')->name('xpress.vehicles.edit');
        Route::put('vehicles/{vehicle}', 'update')->name('xpress.vehicles.update');
        Route::delete('vehicles/{vehicle}/delete', 'destroy')->name('xpress.vehicles.destroy');
    });

    Route::post('users/{user}/reset-password', [UserPasswordResetController::class, 'store'])->middleware('can:manage-users')->name('users.reset-password.store');
    Route::post('users/{user}/reset-2fa', [UserPasswordResetController::class, 'reset2fa'])->middleware('can:manage-users')->name('users.reset-2fa.store');
});
