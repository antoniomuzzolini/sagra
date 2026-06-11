<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Person extends Authenticatable
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;

    protected $table = 'people';

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
    ];

    protected static function booted(): void
    {
        static::saving(function (Person $person) {
            // A person must be reachable: notifications and magic links need a channel.
            if (blank($person->phone) && blank($person->email)) {
                throw new DomainException('A person needs at least one contact (phone or email).');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function signups(): HasMany
    {
        return $this->hasMany(ShiftSignup::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(PersonRole::class);
    }

    public function magicLinks(): HasMany
    {
        return $this->hasMany(MagicLink::class);
    }

    /**
     * Create a personal access link token (D6: passwordless access).
     *
     * A person has one active link: regenerating revokes the previous
     * one. Returns the plain token, shown only once to the organizer.
     */
    public function createMagicLink(?DateTimeInterface $expiresAt = null): string
    {
        $token = Str::random(48);

        $this->magicLinks()->delete();
        $this->magicLinks()->create([
            'tenant_id' => $this->tenant_id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }
}
