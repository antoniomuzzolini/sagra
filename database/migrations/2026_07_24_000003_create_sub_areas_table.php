<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-reparti (D21 kernel node): a light subdivision of an area — cucina →
 * griglia/friggitoria/primi. Option 1: the area stays the "heavy" unit
 * (family, managers); a sub-area is just a name a shift can belong to. A
 * shift references at most one sub-area of its own area; areas without
 * sub-areas behave exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index('area_id');
        });

        Schema::table('shifts', function (Blueprint $table) {
            // Null = area-level shift (the default). On sub-area deletion the
            // shift falls back to area-level rather than disappearing.
            $table->foreignId('sub_area_id')->nullable()->after('area_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_area_id');
        });

        Schema::dropIfExists('sub_areas');
    }
};
