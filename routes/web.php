<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Manage\ManagerAreaController;
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

// The organizer's tenant-wide calendar (D19: same "Calendario" the manager
// has, scoped to the whole event instead of one area).
Route::get('calendar', CalendarController::class)
    ->middleware(['auth', 'organizer'])->name('calendar');

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
