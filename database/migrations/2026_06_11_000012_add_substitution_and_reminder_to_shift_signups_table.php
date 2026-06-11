<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_signups', function (Blueprint $table) {
            $table->timestampTz('substitution_requested_at')->nullable()->after('assigned_by');
            $table->timestampTz('reminded_at')->nullable()->after('substitution_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('shift_signups', function (Blueprint $table) {
            $table->dropColumn(['substitution_requested_at', 'reminded_at']);
        });
    }
};
