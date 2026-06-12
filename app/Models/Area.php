<?php

namespace App\Models;

use App\Enums\AreaFamily;
use App\Enums\Role;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'family',
    ];

    protected function casts(): array
    {
        return [
            'family' => AreaFamily::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function managerRoles(): HasMany
    {
        return $this->hasMany(PersonRole::class)->where('role', Role::AreaManager);
    }

    /**
     * The people running this area, notification targets for what
     * happens in it.
     *
     * @return Collection<int, Person>
     */
    public function managers(): Collection
    {
        return $this->managerRoles()->with('person')->get()->toBase()->pluck('person')->filter();
    }
}
