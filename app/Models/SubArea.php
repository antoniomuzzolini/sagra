<?php

namespace App\Models;

use Database\Factories\SubAreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sub-reparto within an area (D21). Light by design: just a name a shift
 * can belong to; the area keeps family and managers.
 */
class SubArea extends Model
{
    /** @use HasFactory<SubAreaFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'area_id',
        'name',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}
