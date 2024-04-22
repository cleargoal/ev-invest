<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userId = rand(1,10);
        $operationId = $userId === 1 ? rand(2,3) : 4; // only User #1 can add/remove cars. Rest users just add money
        $multiplier = $operationId === 3 ? -100000 : 100000;
        $amount = $userId === 1 ? rand(5, 20) * $multiplier : rand(2000, 300000);
        return [
            'user_id' => $userId,
            'operation_id' => $operationId,
            'amount' => $amount,
            'created_at' => fake()->dateTimeThisYear(now()),
        ];
    }
}
