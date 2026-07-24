<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A line of an order. Name/price/area are snapshot at sale time so editing or
 * deleting a product later never rewrites past receipts. area/sub-area drive
 * the kitchen screens in slice B.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('unit_price', 8, 2);
            $table->unsignedInteger('quantity');
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_area_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
