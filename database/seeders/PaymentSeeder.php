<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\User;
use App\Services\TotalCalculator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the investors payment seeds with only 1st operation status
     */
    public function run(): void
    {
        $calc = new TotalCalculator();

        for ($i=2; $i <= 10; $i++) { // without 1st user, who is the operator
            $payment = Payment::factory()->make(['user_id' => $i]);
            $calc->createPayment($payment->toArray());
        }
    }
}
