<?php

namespace Database\Seeders;

use App\Services\CalculationService;
use Illuminate\Database\Seeder;

class ContributionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        (new CalculationService())->seeding();
    }
}
