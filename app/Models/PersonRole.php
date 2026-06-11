<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\PersonRoleFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonRole extends Model
{
    /** @use HasFactory<PersonRoleFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'person_id',
        'event_id',
        'role',
        'area_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PersonRole $personRole) {
            if ($personRole->role === Role::AreaManager && $personRole->area_id === null) {
                throw new DomainException('An area manager role must be scoped to an area.');
            }

            if ($personRole->role === Role::Organizer && $personRole->area_id !== null) {
                throw new DomainException('An organizer role is event-wide and cannot be scoped to an area.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
