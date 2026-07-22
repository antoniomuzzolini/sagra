<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D19: one identity table. `people` becomes the single person entity,
 * with an optional password and an organizer flag. Organizers, which
 * used to live in their own `users` table (web guard), are folded into
 * `people`; the `users` table is retired. Area managers stay modelled by
 * `person_roles` (area-scoped). Roles are orthogonal to identity, so any
 * person — volunteer, manager or organizer — can sign up for shifts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->timestamp('email_verified_at')->nullable()->after('password');
            $table->boolean('is_organizer')->default(false)->after('email_verified_at');
        });

        // Fold existing organizer accounts into `people`. Match by email
        // inside the tenant (an organizer already present as a person keeps
        // one identity); otherwise create the person.
        if (Schema::hasTable('users')) {
            foreach (DB::table('users')->get() as $user) {
                if ($user->tenant_id === null) {
                    continue; // stray seed accounts without an association
                }

                $existingId = DB::table('people')
                    ->where('tenant_id', $user->tenant_id)
                    ->where('email', $user->email)
                    ->value('id');

                $account = [
                    'password' => $user->password,
                    'email_verified_at' => $user->email_verified_at,
                    'is_organizer' => true,
                    'remember_token' => $user->remember_token,
                    'updated_at' => now(),
                ];

                if ($existingId !== null) {
                    DB::table('people')->where('id', $existingId)->update($account);
                } else {
                    DB::table('people')->insert(array_merge($account, [
                        'tenant_id' => $user->tenant_id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'created_at' => $user->created_at ?? now(),
                    ]));
                }
            }
        }

        // On existing installs `shift_signups.assigned_by` still points at
        // `users`; that table is going away and its ids no longer map, so
        // reset the column and re-point it at `people` (D19). Fresh installs
        // already build it against `people` (see the create migration).
        // SQLite can't drop a column tied to a foreign key, but a fresh
        // SQLite schema never needs this step.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('shift_signups', function (Blueprint $table) {
                $table->dropColumn('assigned_by');
            });
            Schema::table('shift_signups', function (Blueprint $table) {
                $table->foreignId('assigned_by')->nullable()->after('assigned_at')
                    ->constrained('people')->nullOnDelete();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::drop('users');
        }
    }

    public function down(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('shift_signups', function (Blueprint $table) {
                $table->dropColumn('assigned_by');
            });
            Schema::table('shift_signups', function (Blueprint $table) {
                $table->foreignId('assigned_by')->nullable()->after('assigned_at')
                    ->constrained('users')->nullOnDelete();
            });
        }

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['password', 'email_verified_at', 'is_organizer']);
        });
    }
};
