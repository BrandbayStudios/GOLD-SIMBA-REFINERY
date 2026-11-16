<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CategorySeeder::class, AdminSeeder::class]);

        // Demonstration records are opt-in outside local development (set SEED_DEMO_DATA=true).
        if (filter_var(env('SEED_DEMO_DATA', app()->environment('local')), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DemoSeeder::class);
        }
    }
}
