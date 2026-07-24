<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordini/Cassa module (D21) — slice A. The listino: per-edition products with
 * a price, optionally tied to the area/sub-area that prepares them (used by
 * the kitchen screens in slice B). Talks only to the core.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('price', 8, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
