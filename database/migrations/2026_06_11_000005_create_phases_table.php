<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['event_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phases');
    }
};
