<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Services\TotalCalculator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = [
            [
                'title' => 'VW e-Golf',
                'produced' => '2015',
                'mileage' => '107000',
                'cost' => '9300',
                'plan_sale' => '12000',
            ],
            [
                'title' => 'Nissan Leaf 40',
                'produced' => '2019',
                'mileage' => '66000',
                'cost' => '14700',
                'plan_sale' => '16500',
            ],
            [
                'title' => 'Nissan Leaf 24',
                'produced' => '2013',
                'mileage' => '120000',
                'cost' => '6700',
                'plan_sale' => '8000',
            ],
            [
                'title' => 'Nissan Leaf 24',
                'produced' => '2014',
                'mileage' => '104000',
                'cost' => '6200',
                'plan_sale' => '7500',
            ],
            [
                'title' => 'Nissan Leaf 24',
                'produced' => '2015',
                'mileage' => '74000',
                'cost' => '7200',
                'plan_sale' => '9000',
            ],
            [
                'title' => 'Renault ZOE 40',
                'produced' => '2017',
                'mileage' => '58000',
                'cost' => '10000',
                'plan_sale' => '12000',
            ],
            [
                'title' => 'Mercedes b250e',
                'produced' => '2015',
                'mileage' => '90000',
                'cost' => '9500',
                'plan_sale' => '12000',
            ],
            [
                'title' => 'Chevrolet Bolt EV',
                'produced' => '2016',
                'mileage' => '96000',
                'cost' => '13000',
                'plan_sale' => '15000',
            ],
            [
                'title' => 'Chevrolet Bolt EV',
                'produced' => '2018',
                'mileage' => '110000',
                'cost' => '13000',
                'plan_sale' => '15000',
            ],
            [
                'title' => 'Mazda MX-30',
                'produced' => '2020',
                'mileage' => '51000',
                'cost' => '13500',
                'plan_sale' => '16000',
            ],
            [
                'title' => 'Toyota Prius PLUGIN',
                'produced' => '2013',
                'mileage' => '230000',
                'cost' => '10500',
                'plan_sale' => '12000',
            ],
        ];

        $calc = new TotalCalculator();
        foreach ($vehicles as $vehicle) {
            $newVehicle = Vehicle::factory()->make([
                'title' => $vehicle['title'],
                'produced' => $vehicle['produced'],
                'mileage' => $vehicle['mileage'],
                'cost' => $vehicle['cost'] * 100,
                'plan_sale' => $vehicle['plan_sale'] * 100,
            ]);
            $calc->buyVehicle($newVehicle->toArray());
        }
    }
}
