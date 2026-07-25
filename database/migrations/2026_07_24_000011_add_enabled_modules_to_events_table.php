<?php

use App\Enums\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event module switches (D21): a plain JSON list of enabled module keys.
 * A list of three-to-five keys doesn't warrant a pivot table. Null means
 * "never configured" and falls back to the defaults in the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('enabled_modules')->nullable()->after('name');
        });

        // Existing editions keep everything they might already be using —
        // switching this on must not make anyone's data disappear.
        DB::table('events')->update([
            'enabled_modules' => json_encode(array_column(Module::cases(), 'value')),
        ]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('enabled_modules');
        });
    }
};
