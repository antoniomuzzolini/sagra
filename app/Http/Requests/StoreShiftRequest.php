<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Shift creation payload, shared by the organizer flow and the area
 * manager flow (D18). Authorization stays in the controllers, which
 * guard different things (tenant vs. managed area).
 */
class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The shift's area comes from the route: {area} on create, the
        // shift's own area on update. A sub-area, if given, must belong to it.
        $areaId = $this->route('area')?->id ?? $this->route('shift')?->area_id;

        return [
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'needed_people' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sub_area_id' => ['nullable', Rule::exists('sub_areas', 'id')->where('area_id', $areaId)],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Indica la data del turno.',
            'start_time.required' => 'Indica l\'ora di inizio.',
            'end_time.required' => 'Indica l\'ora di fine.',
            'needed_people.required' => 'Indica quante persone servono.',
            'needed_people.min' => 'Serve almeno una persona.',
        ];
    }

    /**
     * Start and end instants; a shift ending past midnight (e.g. bar
     * 20:00–01:00) rolls over to the next day instead of failing.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function times(): array
    {
        $startsAt = Carbon::parse($this->validated('date').' '.$this->validated('start_time'));
        $endsAt = Carbon::parse($this->validated('date').' '.$this->validated('end_time'));

        if ($endsAt->lte($startsAt)) {
            $endsAt->addDay();
        }

        return [$startsAt, $endsAt];
    }
}
