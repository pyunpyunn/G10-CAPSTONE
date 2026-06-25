<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
         * Keep the default seeder empty.
         *
         * RESQPERATION must display only the records from the database selected
         * in backend-laravel/.env. Running php artisan db:seed should not add
         * old demo, temporary, or generated records to the shared database.
         *
         * If development test data is needed, use a reviewed SQL proposal or
         * a one-time DB member approved script. Do not add generated records
         * through the default seeder.
         */
    }
}
