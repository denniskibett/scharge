<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // First create roles
        $this->call(RoleSeeder::class);
        
        // Then create users with roles
        $this->call(UserSeeder::class);
    }
}