<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    /**
     * Event dates are derived from its phases: single source of truth.
     */
    public function startsOn(): ?Carbon
    {
        return $this->phases->min('starts_on');
    }

    public function endsOn(): ?Carbon
    {
        return $this->phases->max('ends_on');
    }

    /**
     * The phase containing the given date, if any.
     */
    public function phaseOn(Carbon $date): ?Phase
    {
        $day = $date->toDateString();

        return $this->phases->first(
            fn (Phase $phase) => $day >= $phase->starts_on->toDateString()
                && $day <= $phase->ends_on->toDateString()
        );
    }
}
