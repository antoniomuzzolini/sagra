<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordini/Cassa slice B (comande/KDS): each order line carries its kitchen
 * state, so a sub-area screen can work its own queue. Lines with no area
 * (nothing to prepare, e.g. a bottle handed over at the till) are born served.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('quantity');
            $table->timestampTz('ready_at')->nullable()->after('sub_area_id');

            $table->index(['area_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['area_id', 'status']);
            $table->dropColumn(['status', 'ready_at']);
        });
    }
};
