<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // Phone-only people cannot get an automatic recovery email:
            // this flags their request so the organizer resends the link.
            $table->timestampTz('link_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('link_requested_at');
        });
    }
};
