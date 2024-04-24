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
        $userId = rand(2,10);
        $operationId = rand(4,5);
        $amount = $operationId === 4 ? rand(2500, 20000) : rand(-2000, -10000);
        return [
            'user_id' => $userId,
            'operation_id' => $operationId,
            'amount' => $amount,
            'created_at' => '',
        ];
    }
}
