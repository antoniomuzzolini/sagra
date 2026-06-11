<?php

use App\Http\Controllers\Volunteer\MagicLinkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Short path: these links travel via WhatsApp messages.
Route::get('v/{token}', MagicLinkController::class)->name('magic-link.consume');

Route::get('link-invalid', function () {
    return Inertia::render('Volunteer/LinkInvalid');
})->name('magic-link.invalid');

Route::middleware('auth:volunteer')->group(function () {
    Route::get('me', function (Request $request) {
        $person = $request->user('volunteer');

        return Inertia::render('Volunteer/Home', [
            'person' => ['name' => $person->name],
            'tenant' => ['name' => $person->tenant->name],
        ]);
    })->name('volunteer.home');
});
