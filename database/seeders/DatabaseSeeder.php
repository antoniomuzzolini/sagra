<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\Tenant;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Sagra di prova']);

        Person::factory()->organizer()->for($tenant)->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
