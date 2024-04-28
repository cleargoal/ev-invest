<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Vehicle;
use PHPUnit\Framework\TestCase;

class InvestIncomeTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_create_vehicle(): void
    {
        $vehicle = Vehicle::factory()->make();
        $this->assertIsObject($vehicle);

        $vehArray = [
            'user_id' => $vehicle->user_id,
            'title' => $vehicle->title,
            'produced' => $vehicle->produced,
            'mileage' => $vehicle->mileage,
            'cost' => $vehicle->cost,
            'created_at' => now(),
        ];
        $this->assertIsArray($vehArray);

//        $investors = User::with('lastContribution')->get();
//        var_dump($investors);
    }
}
