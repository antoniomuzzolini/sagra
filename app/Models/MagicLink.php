<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MagicLink extends Model
{
    protected $fillable = [
        'tenant_id',
        'person_id',
        'token_hash',
        'expires_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Only the SHA-256 hash is stored; the plain token lives in the
     * link shared with the volunteer and is never persisted.
     */
    public static function findByToken(string $token): ?self
    {
        return static::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    /**
     * Links are single-use (D17): tapping one consumes it and hands
     * the device over to the long-lived remember session.
     */
    public function isUsed(): bool
    {
        return $this->last_used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function consume(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
