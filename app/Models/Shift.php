<?php

namespace App\Models;

use App\Enums\SignupStatus;
use Database\Factories\ShiftFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'area_id',
        'starts_at',
        'ends_at',
        'needed_people',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'needed_people' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Shift $shift) {
            if ($shift->ends_at->lte($shift->starts_at)) {
                throw new DomainException('A shift cannot end before it starts.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function signups(): HasMany
    {
        return $this->hasMany(ShiftSignup::class);
    }

    /**
     * The phase this shift belongs to, derived from its start date
     * (architectural rule: never persisted, never declared by hand).
     */
    public function phase(): ?Phase
    {
        return $this->area->event->phaseOn($this->starts_at);
    }

    public function assignedCount(): int
    {
        return $this->signups->where('status', SignupStatus::Assigned)->count();
    }

    public function isFullyStaffed(): bool
    {
        return $this->assignedCount() >= $this->needed_people;
    }
}
