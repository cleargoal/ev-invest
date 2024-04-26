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

        $investment = [true, false, false, false, false];
        $buyVehicle = [true, false, false, false, false, false, false, false, false, false,];
        $sellVehicles = [true, false, false, false, ];

        while ($startDay->lte($today)) {
            $currentDate = $startDay->toDateString();
            $isInvestment = Arr::random($investment);

            if ($isInvestment) {
                $payment = Payment::factory()->create(['created_at' => $currentDate]);
                $this->calc->processing($payment);
            }

            $isBuyVehicle = !$isInvestment && Arr::random($buyVehicle);

            if ($isBuyVehicle) {
                $vehicle = Vehicle::factory()->create(['created_at' => $currentDate,]);
                $vehicleData = $vehicle->toArray();
                $vehicleData['operation_id'] = 2;
                $vehicleData['amount'] = $vehicle['cost'];
                $this->calc->createPayment($vehicleData);
            }

            $isSaturday = (Carbon::parse($currentDate)->dayOfWeek === Carbon::SATURDAY);
            $isSellVehicle = !$isInvestment && Arr::random($sellVehicles);
            if ($isSaturday && $isSellVehicle) {
                $available = Vehicle::where('sale_date', null)->get();
                $sellVehicle = $available->count() > 0 ? $available->random() : null;
                if ($sellVehicle) {
                    $price = $sellVehicle->cost + rand(5, 20) * 10000;
                    $this->calc->sellVehicle($sellVehicle, $currentDate, $price);
                }
            }

            $startDay->addDay();
        }

    }
}
