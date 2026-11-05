<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            \Modules\Core\Database\Seeders\CoreDatabaseSeeder::class,
            \Modules\Services\Database\Seeders\ServicesDatabaseSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
