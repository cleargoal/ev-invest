<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ['Nissan Leaf', 'Chevrolet Bolt', 'eGolf', 'WV i3', 'BMW i3'];
        $produced = ['2016', '2017', '2018', '2019'];
        $cost = rand(5, 20) * 100000;

        return [
            'user_id' => 1,
            'title' => $title[rand(0,4)],
            'produced' => $produced[rand(0,3)],
            'mileage' => rand(60000, 190000),
            'cost' => $cost,
            'created_at' => '',
        ];
    }
}
