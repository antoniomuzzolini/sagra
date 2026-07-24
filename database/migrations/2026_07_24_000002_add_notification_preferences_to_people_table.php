<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-person notification opt-out (backlog item 1). A JSON map of
 * notification keys to booleans; a missing key means "on", so the column
 * stays null until someone actually mutes something.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('is_organizer');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
