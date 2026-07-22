<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_signups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('status')->default('available');
            $table->timestampTz('assigned_at')->nullable();
            // Who confirmed the assignment — a person with the right (D19).
            $table->foreignId('assigned_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->unique(['shift_id', 'person_id']);
            $table->index('person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_signups');
    }
};
