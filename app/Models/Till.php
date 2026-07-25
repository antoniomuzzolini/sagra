<?php

namespace App\Models;

use Database\Factories\TillFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A point of sale (Ordini/Cassa). Belongs to an area: that's who runs it and
 * who may configure it. Its menu is a subset of the event's listino — empty
 * means "sells everything".
 */
class Till extends Model
{
    /** @use HasFactory<TillFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'area_id',
        'name',
    ];

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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The products explicitly put on this till's menu.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * What this till actually sells: its own menu, or the whole active listino
     * when no menu was ever composed.
     *
     * @return Builder<Product>
     */
    public function sellableProducts(): Builder
    {
        $query = Product::query()->where('event_id', $this->event_id)->where('active', true);

        return $this->products()->exists()
            ? $query->whereIn('id', $this->products()->select('products.id'))
            : $query;
    }
}
