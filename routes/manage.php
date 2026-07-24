<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AreaManagerController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SignupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'organizer'])->group(function () {
    Route::resource('people', PersonController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('people/{person}/magic-link', [PersonController::class, 'magicLink'])
        ->name('people.magic-link');
    Route::post('people/{person}/account-invite', [PersonController::class, 'accountInvite'])
        ->name('people.account-invite');
    Route::post('invite/regenerate', [PersonController::class, 'regenerateInvite'])
        ->name('people.invite.regenerate');

    Route::resource('events', EventController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('events/{event}/replicate', [EventController::class, 'replicate'])->name('events.replicate');

    Route::post('events/{event}/areas', [AreaController::class, 'store'])->name('areas.store');
    Route::put('areas/{area}', [AreaController::class, 'update'])->name('areas.update');
    Route::delete('areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');

    Route::post('areas/{area}/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::put('shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');

    Route::put('signups/{signup}', [SignupController::class, 'update'])->name('signups.update');
    Route::delete('signups/{signup}', [SignupController::class, 'destroy'])->name('signups.destroy');

    Route::post('areas/{area}/managers', [AreaManagerController::class, 'store'])->name('areas.managers.store');
    Route::delete('person-roles/{personRole}', [AreaManagerController::class, 'destroy'])->name('person-roles.destroy');
});

// Whole-day operations from Gestione turni (D20): open to whoever runs the
// area — the controller authorizes per area (organizer runs them all).
Route::middleware('auth')->group(function () {
    Route::post('areas/{area}/shifts/replicate-day', [ShiftController::class, 'replicateDay'])->name('shifts.replicate-day');
    Route::delete('areas/{area}/shifts/day/{date}', [ShiftController::class, 'destroyDay'])->name('shifts.destroy-day');
});
