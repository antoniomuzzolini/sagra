<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->foreignId('area_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['person_id', 'event_id', 'role', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_roles');
    }
};
