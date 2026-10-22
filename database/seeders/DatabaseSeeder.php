<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events stay enabled: the demo seeder relies on the tenancy and
     * public-id creating hooks, so WithoutModelEvents must not be used here.
     */
    public function run(): void
    {
        $this->call(RakytTravelDemoSeeder::class);
    }
}
