<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Services\TotalCalculator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class VehicleSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $startDay = Carbon::createFromDate(2023, 1, 2);
        $today = Carbon::today();
        $calc = new TotalCalculator();

        while ($startDay->lte($today)) {
            $currentDate = $startDay->toDateString();

            if (Carbon::parse($currentDate)->day === 4) {
                $vehicle = Vehicle::factory()->make(['created_at' => $currentDate,]);
                $calc->buyVehicle($vehicle->toArray());
            }

            $startDay->addDay();
        }

    }
}
