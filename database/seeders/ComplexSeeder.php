<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use App\Services\VehicleService;

class ComplexSeeder extends Seeder
{

    /**
     * Run the database seeds. Complex seeding for all models
     */
    public function run(): void
    {
        $startDay = Carbon::createFromDate(2023, 1, 2);
        $today = Carbon::today();
        $calc = app(VehicleService::class);

        $investment = [true, false, false, false, false, false, false, false,];
        $buyVehicle = [true, false, false, false, false, false, false, false, false, false, false, false, false,];
        $sellVehicles = [true, false, false, false, false, false, false,];

        while ($startDay->lte($today)) {
            $currentDate = $startDay->toDateString();
            $isInvestment = Arr::random($investment);

            if ($isInvestment) {
                $payment = Payment::factory()->make(['created_at' => $currentDate]);
                $calc->createPayment($payment->toArray());
            }

            $isBuyVehicle = !$isInvestment && Arr::random($buyVehicle);

            if ($isBuyVehicle) {
                $vehicle = Vehicle::factory()->make(['created_at' => $currentDate,]);
                $calc->buyVehicle($vehicle->toArray());
            }

            $isSaturday = (Carbon::parse($currentDate)->dayOfWeek === Carbon::SATURDAY); // it Works!
            $isSellVehicle = !$isInvestment && Arr::random($sellVehicles);
            if ($isSaturday && $isSellVehicle) {
                $existCars = Vehicle::where('sale_date', null)->get();
                $sellVehicle = $existCars->count() > 0 ? $existCars->random() : null;
                if ($sellVehicle) {
                    $price = $sellVehicle->cost + rand(5, 20) * 10000;
                    $calc->sellVehicle($sellVehicle, $price, $currentDate);
                }
            }

            $startDay->addDay();
        }

    }
}
