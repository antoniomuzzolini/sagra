<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventContextController;
use App\Http\Controllers\Manage\AreaManagementController;
use App\Http\Controllers\Manage\ManagerAreaController;
use App\Http\Controllers\Manage\ShiftManagementController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplyAttachmentController;
use App\Http\Controllers\SupplyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// PWA manifest, dynamic so the app name stays a single config value.
Route::get('manifest.webmanifest', function () {
    return response()->json([
        'name' => config('app.name'),
        'short_name' => config('app.name'),
        'description' => 'Turni e volontari per sagre e feste.',
        'start_url' => '/me',
        'display' => 'standalone',
        'background_color' => '#18181b',
        'theme_color' => '#18181b',
        'lang' => 'it',
        'icons' => [
            ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ],
    ], 200, ['Content-Type' => 'application/manifest+json']);
})->name('pwa.manifest');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'organizer'])->name('dashboard');

// The organizer's calendar, scoped to the current event (D20).
Route::get('calendar', CalendarController::class)
    ->middleware(['auth', 'organizer'])->name('calendar');

// Gestione aree of the current event (D20): organizer defines areas + managers.
Route::get('manage/areas', AreaManagementController::class)
    ->middleware(['auth', 'organizer'])->name('manage.areas');

// Organization-wide settings (organizer only).
Route::middleware(['auth', 'organizer'])->group(function () {
    Route::get('organization', [OrganizationController::class, 'edit'])->name('organization.edit');
    Route::put('organization', [OrganizationController::class, 'update'])->name('organization.update');
    Route::post('organization/test-notification', [OrganizationController::class, 'sendTest'])
        ->name('organization.test-notification');
});

// Switch the current event (D20).
Route::post('current-event', [EventContextController::class, 'update'])
    ->middleware('auth')->name('current-event.update');

// Gestione turni (D20): organizer or area manager, scoped to the current event.
Route::get('manage/shifts', ShiftManagementController::class)
    ->middleware(['auth', 'staff'])->name('manage.shifts');

// Forniture module (first vertical module, D21): organizer or area manager,
// scoped to the current event and the areas the person runs.
Route::middleware(['auth', 'staff'])->group(function () {
    Route::get('forniture', [SupplyController::class, 'index'])->name('supplies.index');
    Route::post('supplies', [SupplyController::class, 'store'])->name('supplies.store');
    Route::put('supplies/{supply}', [SupplyController::class, 'update'])->name('supplies.update');
    Route::delete('supplies/{supply}', [SupplyController::class, 'destroy'])->name('supplies.destroy');
    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    Route::post('supplies/{supply}/attachments', [SupplyAttachmentController::class, 'store'])->name('supply-attachments.store');
    Route::get('supply-attachments/{attachment}', [SupplyAttachmentController::class, 'download'])->name('supply-attachments.download');
    Route::delete('supply-attachments/{attachment}', [SupplyAttachmentController::class, 'destroy'])->name('supply-attachments.destroy');
});

// Area-manager pages (D19): the same sidebar shell as the organizer, scoped
// to the areas the person runs. Read views for now — editing stays on /me.
Route::middleware(['auth', 'manager'])->prefix('manage')->name('manage.')->group(function () {
    Route::get('overview', [ManagerAreaController::class, 'overview'])->name('overview');
    Route::get('calendar', [ManagerAreaController::class, 'calendar'])->name('calendar');
    Route::get('people', [ManagerAreaController::class, 'people'])->name('people');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/volunteer.php';
require __DIR__.'/manage.php';
