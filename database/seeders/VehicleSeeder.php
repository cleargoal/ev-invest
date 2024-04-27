<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use App\Services\TotalCalculator;

class VehicleSeeder extends Seeder
{

    private TotalCalculator $calc;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $startDay = Carbon::createFromDate(2023, 1, 2);
        $today = Carbon::today();
        $this->calc = new TotalCalculator();

        $investment = [true, false, false, false, false, false, false, false,];
        $buyVehicle = [true, false, false, false, false, false, false, false, false, false, false, false, false,];
        $sellVehicles = [true, false, false, false, false, false, false,];

        while ($startDay->lte($today)) {
            $currentDate = $startDay->toDateString();
            $isInvestment = Arr::random($investment);

            if ($isInvestment) {
                $payment = Payment::factory()->make(['created_at' => $currentDate]);
                $this->calc->createPayment($payment->toArray());
            }

            $isBuyVehicle = !$isInvestment && Arr::random($buyVehicle);

            if ($isBuyVehicle) {
                $vehicle = Vehicle::factory()->make(['created_at' => $currentDate,]);
                $this->calc->buyVehicle($vehicle->toArray());
            }

            $isSaturday = (Carbon::parse($currentDate)->dayOfWeek === Carbon::SATURDAY); // it Works!
            $isSellVehicle = !$isInvestment && Arr::random($sellVehicles);
            if ($isSaturday && $isSellVehicle) {
                $existCars = Vehicle::where('sale_date', null)->get();
                $sellVehicle = $existCars->count() > 0 ? $existCars->random() : null;
                if ($sellVehicle) {
                    $price = $sellVehicle->cost + rand(5, 20) * 10000;
                    $this->calc->sellVehicle($sellVehicle, $currentDate, $price);
                }
            }

            $startDay->addDay();
        }

    }
}
