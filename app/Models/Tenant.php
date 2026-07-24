<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * An organization-wide setting (D5: a handful of switches, not a
     * config tree). Unset keys fall back to their default.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * The shareable invite token (D16): volunteers self-register through
     * it, so the organizer shares one link instead of one per person.
     */
    public function inviteToken(): string
    {
        if ($this->invite_token === null) {
            $this->regenerateInviteToken();
        }

        return $this->invite_token;
    }

    public function regenerateInviteToken(): void
    {
        $this->forceFill(['invite_token' => Str::random(40)])->save();
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
