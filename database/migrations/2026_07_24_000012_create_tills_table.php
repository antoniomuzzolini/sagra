<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points of sale (Ordini/Cassa). A till belongs to an area, which covers both
 * organisations: a standalone "Cassa" reparto whose responsabile configures
 * every till, or one till per reparto that each responsabile runs. The area
 * also decides who may configure it — no new permission concept.
 *
 * Tills are optional: with none, the page sells the whole listino as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index('event_id');
        });

        // A till's menu. No rows for a till = it sells the whole listino, so a
        // single-till sagra configures nothing.
        Schema::create('product_till', function (Blueprint $table) {
            $table->id();
            $table->foreignId('till_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->unique(['till_id', 'product_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('till_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('till_id');
        });

        Schema::dropIfExists('product_till');
        Schema::dropIfExists('tills');
    }
};
