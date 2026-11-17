<?php

use App\Http\Controllers\Admin\AllBookingController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BookingCustomerController;
use App\Http\Controllers\Admin\MaintenanceLogController;
use App\Http\Controllers\Admin\PaymentController;
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

    Route::middleware('can:manage-bookings')->group(function () {
        Route::get('booking-customers', [BookingCustomerController::class, 'index'])->name('xpress.booking-customers.index');
        Route::post('booking-customers', [BookingCustomerController::class, 'store'])->name('xpress.booking-customers.store');
        Route::get('bookings', [BookingController::class, 'index'])->name('xpress.bookings.index');
        Route::get('all-bookings', [AllBookingController::class, 'index'])->name('xpress.all-bookings.index');
        Route::get('bookings/create', [BookingController::class, 'create'])->name('xpress.bookings.create');
        Route::post('bookings', [BookingController::class, 'store'])->name('xpress.bookings.store');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('xpress.bookings.show');
        Route::get('bookings/{booking}/edit', [BookingController::class, 'edit'])->name('xpress.bookings.edit');
        Route::get('bookings/{booking}/agreement', [BookingController::class, 'agreement'])->name('xpress.bookings.agreement');
        Route::get('bookings/{booking}/agreement/pdf', [BookingController::class, 'downloadAgreement'])->name('xpress.bookings.agreement.pdf');
        Route::post('bookings/{booking}/agreement/payments', [PaymentController::class, 'storeAgreement'])->middleware('can:manage-payments')->name('xpress.bookings.agreement.payments.store');
        Route::put('bookings/{booking}', [BookingController::class, 'update'])->name('xpress.bookings.update');
        Route::delete('bookings/{booking}/delete', [BookingController::class, 'destroy'])->name('xpress.bookings.destroy');
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

    Route::controller(PaymentController::class)->middleware('can:manage-payments')->group(function () {
        Route::get('payments', 'index')->name('xpress.payments.index');
        Route::get('payments/bookings', 'bookings')->name('xpress.payments.bookings');
        Route::get('payments/create', 'create')->name('xpress.payments.create');
        Route::post('payments', 'store')->name('xpress.payments.store');
        Route::get('payments/{payment}', 'show')->name('xpress.payments.show');
        Route::get('payments/{payment}/edit', 'edit')->name('xpress.payments.edit');
        Route::put('payments/{payment}', 'update')->name('xpress.payments.update');
        Route::delete('payments/{payment}/delete', 'destroy')->name('xpress.payments.destroy');
    });

    Route::controller(MaintenanceLogController::class)->middleware('can:manage-maintenance-logs')->group(function () {
        Route::get('maintenance-logs', 'index')->name('xpress.maintenance-logs.index');
        Route::get('maintenance-logs/vehicles', 'vehicles')->name('xpress.maintenance-logs.vehicles');
        Route::get('maintenance-logs/create', 'create')->name('xpress.maintenance-logs.create');
        Route::post('maintenance-logs', 'store')->name('xpress.maintenance-logs.store');
        Route::get('maintenance-logs/{maintenanceLog}', 'show')->name('xpress.maintenance-logs.show');
        Route::get('maintenance-logs/{maintenanceLog}/edit', 'edit')->name('xpress.maintenance-logs.edit');
        Route::put('maintenance-logs/{maintenanceLog}', 'update')->name('xpress.maintenance-logs.update');
        Route::delete('maintenance-logs/{maintenanceLog}/delete', 'destroy')->name('xpress.maintenance-logs.destroy');
    });

    Route::post('users/{user}/reset-password', [UserPasswordResetController::class, 'store'])->middleware('can:manage-users')->name('users.reset-password.store');
    Route::post('users/{user}/reset-2fa', [UserPasswordResetController::class, 'reset2fa'])->middleware('can:manage-users')->name('users.reset-2fa.store');
});
