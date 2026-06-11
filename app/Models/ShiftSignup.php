<?php

namespace App\Models;

use App\Enums\SignupStatus;
use Database\Factories\ShiftSignupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSignup extends Model
{
    /** @use HasFactory<ShiftSignupFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'shift_id',
        'person_id',
        'status',
        'assigned_at',
        'assigned_by',
        'substitution_requested_at',
        'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SignupStatus::class,
            'assigned_at' => 'datetime',
            'substitution_requested_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
