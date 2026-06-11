<?php

namespace App\Models;

use App\Enums\PhaseType;
use Database\Factories\PhaseFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Phase extends Model
{
    /** @use HasFactory<PhaseFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'type',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'type' => PhaseType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Phase $phase) {
            if ($phase->ends_on->lt($phase->starts_on)) {
                throw new DomainException('A phase cannot end before it starts.');
            }

            // Phases of an event must not overlap: shift→phase derivation
            // relies on each date belonging to at most one phase.
            $overlaps = static::query()
                ->where('event_id', $phase->event_id)
                ->when($phase->exists, fn ($query) => $query->whereKeyNot($phase->getKey()))
                ->where('starts_on', '<=', $phase->ends_on)
                ->where('ends_on', '>=', $phase->starts_on)
                ->exists();

            if ($overlaps) {
                throw new DomainException('Phases of the same event cannot overlap.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
