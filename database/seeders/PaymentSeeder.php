<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Services\CalculationService;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the investors payment seeds with only 1st operation status
     */
    public function run(): void
    {
        $calc = new CalculationService();

        for ($i=2; $i <= 10; $i++) { // without 1st user, who is the operator
            $payment = Payment::factory()->make(['user_id' => $i]);
            $calc->createPayment($payment->toArray());
        }
    }
}
