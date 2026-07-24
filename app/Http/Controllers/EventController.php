<?php

namespace App\Http\Controllers;

use App\Enums\PhaseType;
use App\Enums\Role;
use App\Models\Event;
use App\Models\PersonRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $events = Event::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('phases')
            ->withCount('areas')
            ->latest('id')
            ->get();

        $years = $events->map(fn (Event $event) => $event->year())
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $requested = $request->integer('year');
        $selectedYear = $years->contains($requested)
            ? $requested
            // Closest edition to today; ties go to the upcoming one.
            : $years->sortBy(fn (int $year) => [abs($year - now()->year), -$year])->first();

        return Inertia::render('Events/Index', [
            'events' => $events
                // Events without phases have no year yet: keep them visible.
                ->filter(fn (Event $event) => $event->year() === $selectedYear || $event->year() === null)
                ->values()
                ->map(fn (Event $event) => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'startsOn' => $event->startsOn()?->toDateString(),
                    'endsOn' => $event->endsOn()?->toDateString(),
                    'areasCount' => $event->areas_count,
                ]),
            'years' => $years,
            'selectedYear' => $selectedYear,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEvent($request);

        $event = DB::transaction(function () use ($request, $data) {
            $event = Event::create([
                'tenant_id' => $request->user()->tenant_id,
                'name' => $data['name'],
            ]);
            $this->syncPhases($event, $data['phases']);

            return $event;
        });

        return redirect()->route('events.show', $event);
    }

    public function show(Request $request, Event $event): Response
    {
        $this->authorizeTenant($request, $event);

        // The edition editor (D20): name + phases. Areas, responsabili and
        // shifts are managed in the dedicated Aree and Gestione turni pages.
        $event->load([
            'phases' => fn ($query) => $query->orderBy('starts_on'),
            'areas' => fn ($query) => $query->orderBy('name'),
        ]);

        return Inertia::render('Events/Show', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'phases' => $event->phases->map(fn ($phase) => [
                    'id' => $phase->id,
                    'type' => $phase->type->value,
                    'starts_on' => $phase->starts_on->toDateString(),
                    'ends_on' => $phase->ends_on->toDateString(),
                ]),
                'areas' => $event->areas->map(fn ($area) => ['id' => $area->id, 'name' => $area->name]),
            ],
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeTenant($request, $event);

        $data = $this->validateEvent($request);

        DB::transaction(function () use ($event, $data) {
            $event->update(['name' => $data['name']]);
            $event->phases()->delete();
            $this->syncPhases($event, $data['phases']);
        });

        return back();
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeTenant($request, $event);

        $event->delete();

        return redirect()->route('events.index');
    }

    /**
     * Duplicate a past edition (D15, MVP §5): copy the skeleton — phases,
     * areas, responsabili and shifts — remapped onto new dates by a whole-day
     * offset so the relative structure (weekends, gaps, shift times) is kept.
     * Availabilities and assignments are personal to that year and never
     * travel.
     */
    public function replicate(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeTenant($request, $event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
        ], [
            'name.required' => 'Il nome della nuova edizione è obbligatorio.',
            'starts_on.required' => 'Indica la data di inizio della nuova edizione.',
        ]);

        $event->load(['phases', 'areas.shifts', 'areas.managerRoles.person']);

        $sourceStart = $event->startsOn();

        if ($sourceStart === null) {
            throw ValidationException::withMessages([
                'starts_on' => 'Questo evento non ha fasi da replicare.',
            ]);
        }

        // Whole-day shift from the source's first day to the chosen new start.
        $offset = (int) $sourceStart->copy()->startOfDay()
            ->diffInDays(Carbon::parse($data['starts_on'])->startOfDay(), false);

        $new = DB::transaction(function () use ($event, $data, $offset) {
            $new = Event::create([
                'tenant_id' => $event->tenant_id,
                'name' => $data['name'],
            ]);

            foreach ($event->phases as $phase) {
                $new->phases()->create([
                    'tenant_id' => $event->tenant_id,
                    'type' => $phase->type,
                    'starts_on' => $phase->starts_on->copy()->addDays($offset),
                    'ends_on' => $phase->ends_on->copy()->addDays($offset),
                ]);
            }

            foreach ($event->areas as $area) {
                $newArea = $new->areas()->create([
                    'tenant_id' => $event->tenant_id,
                    'name' => $area->name,
                    'family' => $area->family,
                ]);

                // Responsabili travel; a role whose person is gone (soft
                // deleted) is skipped rather than resurrected.
                foreach ($area->managerRoles as $role) {
                    if ($role->person === null) {
                        continue;
                    }

                    PersonRole::create([
                        'tenant_id' => $event->tenant_id,
                        'person_id' => $role->person_id,
                        'event_id' => $new->id,
                        'role' => Role::AreaManager,
                        'area_id' => $newArea->id,
                    ]);
                }

                foreach ($area->shifts as $shift) {
                    $newArea->shifts()->create([
                        'tenant_id' => $event->tenant_id,
                        'starts_at' => $shift->starts_at->copy()->addDays($offset),
                        'ends_at' => $shift->ends_at->copy()->addDays($offset),
                        'needed_people' => $shift->needed_people,
                        'notes' => $shift->notes,
                    ]);
                }
            }

            return $new;
        });

        return redirect()->route('events.show', $new);
    }

    private function validateEvent(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phases' => ['required', 'array', 'min:1'],
            'phases.*.type' => ['required', Rule::enum(PhaseType::class)],
            'phases.*.starts_on' => ['required', 'date'],
            'phases.*.ends_on' => ['required', 'date', 'after_or_equal:phases.*.starts_on'],
        ], [
            'name.required' => 'Il nome è obbligatorio.',
            'phases.required' => 'Serve almeno una fase.',
            'phases.*.type.required' => 'Scegli il tipo di fase.',
            'phases.*.starts_on.required' => 'Indica la data di inizio.',
            'phases.*.ends_on.required' => 'Indica la data di fine.',
            'phases.*.ends_on.after_or_equal' => 'Una fase non può finire prima di iniziare.',
        ]);

        $sorted = collect($data['phases'])->sortBy('starts_on')->values();
        $sorted->sliding(2)->each(function ($pair) {
            if ($pair[1]['starts_on'] <= $pair[0]['ends_on']) {
                throw ValidationException::withMessages([
                    'phases' => 'Le fasi non possono sovrapporsi.',
                ]);
            }
        });

        return $data;
    }

    private function syncPhases(Event $event, array $phases): void
    {
        foreach ($phases as $phase) {
            $event->phases()->create([...$phase, 'tenant_id' => $event->tenant_id]);
        }
    }
}
