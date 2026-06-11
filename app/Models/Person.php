<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

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
}
