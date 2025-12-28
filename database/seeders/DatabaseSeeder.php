<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin user
        User::factory()->withPersonalTeam()->create([
            'name' => 'Admin User',
            'email' => 'admin@admin.com',
        ]);

        // Additional test users
        User::factory(5)->withPersonalTeam()->create();

        // Run Bouncer seeder
        $this->call(BouncerSeeder::class);

        // Seed families with members, relationships, and timeline entries
        $this->call(FamilySeeder::class);
    }
}
