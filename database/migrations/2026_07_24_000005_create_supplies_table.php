<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A supply entry (Forniture): what was bought/rented/borrowed for an edition,
 * optionally tied to an area/sub-area and a supplier. Scoped to an event; the
 * area/sub-area/supplier links go null (not cascade) so the history survives
 * their removal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // App\Enums\SupplyType
            $table->string('description');
            $table->decimal('cost', 10, 2)->nullable();
            $table->date('acquired_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
