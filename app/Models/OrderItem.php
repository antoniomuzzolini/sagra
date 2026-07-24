<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of an order, with product data snapshot at sale time.
 */
class OrderItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'order_id',
        'product_id',
        'name',
        'unit_price',
        'quantity',
        'area_id',
        'sub_area_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
