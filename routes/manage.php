<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('people', PersonController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('people/{person}/magic-link', [PersonController::class, 'magicLink'])
        ->name('people.magic-link');

    Route::resource('events', EventController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::post('events/{event}/areas', [AreaController::class, 'store'])->name('areas.store');
    Route::put('areas/{area}', [AreaController::class, 'update'])->name('areas.update');
    Route::delete('areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');

    Route::post('areas/{area}/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
});
