<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forniture — phase 2: attachments (invoices/notes) on a supply. Files live on
 * the private 'local' disk, tenant-scoped, reachable only through an authorized
 * download route — never web-public.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supply_id')->constrained('supplies')->cascadeOnDelete();
            $table->string('path');
            $table->string('name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('supply_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_attachments');
    }
};
