<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\PersonFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;

class Person extends Authenticatable
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    use HasPushSubscriptions;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'people';

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'password',
        'is_organizer',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'is_organizer' => 'boolean',
            'link_requested_at' => 'datetime',
        ];
    }

    /**
     * Store emails normalised (lower-case, trimmed) so login and lookups are
     * case-insensitive — the same address must match however it was typed.
     */
    protected function setEmailAttribute(?string $value): void
    {
        $value = $value === null ? null : mb_strtolower(trim($value));
        $this->attributes['email'] = $value === '' ? null : $value;
    }

    /**
     * Organizer (D19): tenant-wide capability, distinct from the
     * area-scoped manager roles in `person_roles`. Orthogonal to identity —
     * an organizer is still a person who can sign up for shifts.
     */
    public function isOrganizer(): bool
    {
        return (bool) $this->is_organizer;
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
     * The areas a person may run. An organizer runs the whole event, so they
     * manage every area of their tenant (D19: "responsabile di tutti i
     * reparti"); everyone else manages the areas their roles are scoped to.
     */
    public function managesArea(int $areaId): bool
    {
        if ($this->isOrganizer()) {
            return Area::query()
                ->whereKey($areaId)
                ->where('tenant_id', $this->tenant_id)
                ->exists();
        }

        return $this->roles()
            ->where('role', Role::AreaManager)
            ->where('area_id', $areaId)
            ->exists();
    }

    /**
     * @return Collection<int, int>
     */
    public function managedAreaIds(): Collection
    {
        if ($this->isOrganizer()) {
            return Area::query()->where('tenant_id', $this->tenant_id)->pluck('id');
        }

        return $this->roles()
            ->where('role', Role::AreaManager)
            ->pluck('area_id');
    }

    /**
     * Create a personal access link token (D6: passwordless access).
     *
     * Links are single-use and short-lived (D17): once tapped, the long
     * remember session takes over. A person has one pending link;
     * regenerating revokes the previous one and the remember sessions
     * on every device (kill switch). Returns the plain token, shown
     * only once to the organizer.
     */
    public function createMagicLink(?DateTimeInterface $expiresAt = null): string
    {
        $token = Str::random(48);

        $this->magicLinks()->delete();
        $this->magicLinks()->create([
            'tenant_id' => $this->tenant_id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);

        $this->setRememberToken(Str::random(60));
        $this->save();

        return $token;
    }
}
