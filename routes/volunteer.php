<?php

use App\Http\Controllers\Volunteer\HomeController;
use App\Http\Controllers\Volunteer\MagicLinkController;
use App\Http\Controllers\Volunteer\SignupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Short path: these links travel via WhatsApp messages.
Route::get('v/{token}', MagicLinkController::class)->name('magic-link.consume');

Route::get('link-invalid', function () {
    return Inertia::render('Volunteer/LinkInvalid');
})->name('magic-link.invalid');

Route::middleware('auth:volunteer')->group(function () {
    Route::get('me', HomeController::class)->name('volunteer.home');
    Route::post('me/shifts/{shift}/signup', [SignupController::class, 'store'])->name('volunteer.signup');
    Route::delete('me/shifts/{shift}/signup', [SignupController::class, 'destroy'])->name('volunteer.signup.withdraw');
});
