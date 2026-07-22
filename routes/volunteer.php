<?php

use App\Http\Controllers\Volunteer\HomeController;
use App\Http\Controllers\Volunteer\JoinController;
use App\Http\Controllers\Volunteer\MagicLinkController;
use App\Http\Controllers\Volunteer\ManagerPeopleController;
use App\Http\Controllers\Volunteer\ManagerShiftController;
use App\Http\Controllers\Volunteer\ProfileController;
use App\Http\Controllers\Volunteer\PushSubscriptionController;
use App\Http\Controllers\Volunteer\RecoveryController;
use App\Http\Controllers\Volunteer\SignupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Short paths: these links travel via WhatsApp messages.
Route::get('v/{token}', [MagicLinkController::class, 'consume'])->name('magic-link.consume');
Route::post('v/{token}/switch', [MagicLinkController::class, 'switch'])->name('magic-link.switch');

Route::get('join/{token}', [JoinController::class, 'show'])->name('join.show');
Route::post('join/{token}', [JoinController::class, 'store'])->name('join.store');

Route::get('link-invalid', function () {
    return Inertia::render('Volunteer/LinkInvalid');
})->name('magic-link.invalid');

Route::get('recover', [RecoveryController::class, 'show'])->name('volunteer.recover');
Route::post('recover', [RecoveryController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('volunteer.recover.store');

Route::middleware('auth')->group(function () {
    Route::get('me', HomeController::class)->name('volunteer.home');
    Route::post('me/shifts/{shift}/signup', [SignupController::class, 'store'])->name('volunteer.signup');
    Route::delete('me/shifts/{shift}/signup', [SignupController::class, 'destroy'])->name('volunteer.signup.withdraw');
    Route::post('me/shifts/{shift}/substitution', [SignupController::class, 'requestSubstitution'])->name('volunteer.substitution');
    Route::delete('me/shifts/{shift}/substitution', [SignupController::class, 'cancelSubstitution'])->name('volunteer.substitution.cancel');
    Route::put('me/signups/{signup}', [SignupController::class, 'moderate'])->name('volunteer.signups.moderate');
    Route::delete('me/signups/{signup}', [SignupController::class, 'remove'])->name('volunteer.signups.remove');
    Route::put('me/contact', [ProfileController::class, 'updateContact'])->name('volunteer.contact');
    Route::post('me/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('volunteer.push.store');
    Route::delete('me/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('volunteer.push.destroy');

    // Area managers run their own area (D18); the controllers scope every
    // action to the areas the person manages, so one guard is enough (D19).
    Route::post('me/people', [ManagerPeopleController::class, 'store'])->name('volunteer.people.store');
    Route::post('me/areas/{area}/shifts', [ManagerShiftController::class, 'store'])->name('volunteer.shifts.store');
    Route::put('me/shifts/{shift}', [ManagerShiftController::class, 'update'])->name('volunteer.shifts.update');
    Route::delete('me/shifts/{shift}', [ManagerShiftController::class, 'destroy'])->name('volunteer.shifts.destroy');
    Route::post('me/shifts/{shift}/assign', [SignupController::class, 'assign'])->name('volunteer.signups.assign');
});
