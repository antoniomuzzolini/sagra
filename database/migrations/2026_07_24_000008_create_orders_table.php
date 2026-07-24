<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order rung up at the till (Ordini/Cassa slice A). Per-event sequential
 * number for reference; cash + "segna pagato" for now (electronic payments
 * and fiscal receipts are later). created_by keeps the till operator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->decimal('total', 10, 2)->default(0);
            $table->boolean('paid')->default(true);
            $table->string('payment_method')->default('cash');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
