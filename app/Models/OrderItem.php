<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
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
        'status',
        'area_id',
        'sub_area_id',
        'ready_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'status' => OrderItemStatus::class,
            'ready_at' => 'datetime',
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
