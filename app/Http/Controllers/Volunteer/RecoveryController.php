<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Notifications\MagicLinkRecovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service access recovery (D17): a volunteer who lost their
 * session enters the contact they registered and gets a fresh link —
 * by email automatically, or by flagging the request to the organizer
 * when only a phone is on file (no SMS/WhatsApp sending in the MVP).
 */
class RecoveryController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Volunteer/Recover');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['contact' => ['required', 'string', 'max:255']],
            ['contact.required' => 'Scrivi il telefono o l\'email con cui sei registrato.'],
        );

        // The response never reveals whether the contact exists.
        foreach ($this->matches($data['contact']) as $person) {
            if ($person->email) {
                $url = route('magic-link.consume', $person->createMagicLink());
                $person->notify(new MagicLinkRecovery($url, $person->tenant->name));
            } else {
                $person->forceFill(['link_requested_at' => now()])->save();
            }
        }

        return back()->with('recoveryRequested', true);
    }

    /**
     * A contact may legitimately appear in more than one tenant
     * (uniqueness is per tenant): recover them all.
     *
     * @return Collection<int, Person>
     */
    private function matches(string $contact): Collection
    {
        if (str_contains($contact, '@')) {
            return Person::query()->where('email', $contact)->get();
        }

        // Phones are stored as typed: compare digits, ignoring formatting
        // and a possible country prefix. Fine at MVP scale.
        $digits = preg_replace('/\D/', '', $contact);

        if (strlen($digits) < 9) {
            return new Collection;
        }

        $tail = substr($digits, -9);

        return Person::query()
            ->whereNotNull('phone')
            ->get()
            ->filter(fn (Person $person) => str_ends_with(preg_replace('/\D/', '', $person->phone), $tail))
            ->values();
    }
}
