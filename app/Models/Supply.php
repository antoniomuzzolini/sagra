<?php

namespace App\Models;

use App\Enums\SupplyType;
use Database\Factories\SupplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supply entry (Forniture): something bought/rented/borrowed for an edition,
 * optionally tied to an area/sub-area and a supplier.
 */
class Supply extends Model
{
    /** @use HasFactory<SupplyFactory> */
    use HasFactory;

    protected $table = 'supplies';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'area_id',
        'sub_area_id',
        'supplier_id',
        'type',
        'description',
        'cost',
        'acquired_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => SupplyType::class,
            'cost' => 'decimal:2',
            'acquired_on' => 'date',
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

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function subArea(): BelongsTo
    {
        return $this->belongsTo(SubArea::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
