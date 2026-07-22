<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Emails are now stored lower-case so login is case-insensitive. Bring
 * existing rows in line — otherwise an account created with a capitalised
 * email can't be logged into with the natural lower-case form.
 *
 * If two rows in a tenant differ only by case (extremely unlikely at this
 * scale), the unique index would reject the update; the row is left as-is
 * so the migration still completes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('people')->whereNotNull('email')->orderBy('id')
            ->each(function ($person) {
                $lower = mb_strtolower(trim((string) $person->email));

                if ($lower === $person->email) {
                    return;
                }

                try {
                    DB::table('people')->where('id', $person->id)->update(['email' => $lower]);
                } catch (Throwable) {
                    // A case-only collision: leave this row untouched.
                }
            });
    }

    public function down(): void
    {
        // Lower-casing isn't reversible; nothing to undo.
    }
};
