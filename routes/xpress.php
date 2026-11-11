<?php

use App\Http\Controllers\Xpress\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', '2fa', 'force.password.change'])->group(function () {

    Route::controller(WarehouseController::class)->group(function () {
        Route::get('warehouse', 'index')->name('xpress.warehouse.index');
        Route::get('warehouse/bin', 'bin')->name('xpress.warehouse.bin');
        Route::post('warehouse/bin/{deletedModel}/restore', 'restore')->name('xpress.warehouse.restore');
        Route::get('warehouse/create', 'create')->name('xpress.warehouse.create');
        Route::post('warehouse', 'store')->name('xpress.warehouse.store');
        Route::get('warehouse/{warehouse}/edit', 'edit')->name('xpress.warehouse.edit');
        Route::put('warehouse/{warehouse}', 'update')->name('xpress.warehouse.update');
        Route::delete('/warehouse/{warehouse}', 'destroy')->name('xpress.warehouse.destroy');
    });
});
