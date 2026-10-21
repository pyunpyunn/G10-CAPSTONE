<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class HqPersonnelSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn(
            'Default committee login accounts are not generated. Assign the seeded Annex H roles to real accounts through Account Management.',
        );
    }
}
