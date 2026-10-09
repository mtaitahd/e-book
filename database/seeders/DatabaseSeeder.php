<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Development-only data. No production or imported data.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
