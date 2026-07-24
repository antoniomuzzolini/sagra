<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file attached to a supply (Forniture phase 2): invoice or note, on the
 * private 'local' disk. Deleting the record removes the file too.
 */
class SupplyAttachment extends Model
{
    public const DISK = 'local';

    protected $fillable = [
        'tenant_id',
        'supply_id',
        'path',
        'name',
        'mime',
        'size',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SupplyAttachment $attachment) {
            Storage::disk(self::DISK)->delete($attachment->path);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}
