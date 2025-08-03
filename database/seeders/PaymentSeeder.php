<?php

namespace Database\Seeders;

use App\Enums\OperationType;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Create realistic payments for each vehicle purchase
     * This simulates the real buying process where company buys vehicles
     */
    public function run(): void
    {
        $paymentService = app(PaymentService::class);
        $operatorUser = User::role('operator')->first();

        if (!$operatorUser) {
            $this->command->error('operator user not found. Please run UserSeeder first.');
            return;
        }

        // Get all vehicles to create payments for their purchases
        $vehicles = Vehicle::all();

        if ($vehicles->isEmpty()) {
            $this->command->error('No vehicles found. Please run VehicleSeeder first.');
            return;
        }

        $this->command->info("Creating payments for {$vehicles->count()} vehicle purchases...");

        foreach ($vehicles as $vehicle) {
            // Create payment for vehicle purchase (BUY_CAR operation)
            $paymentData = [
                'user_id' => $operatorUser->id,
                'operation_id' => OperationType::BUY_CAR->value,
                'amount' => $vehicle->cost, // Positive because we're spending pool money to buy assets
                'confirmed' => true,
                'created_at' => $vehicle->created_at ?? Carbon::now(),
                'vehicle_id' => $vehicle->id,
            ];

            $paymentService->createPayment($paymentData);

            $this->command->info("Created BUY_CAR payment for {$vehicle->title}: \${$vehicle->cost}");
        }

        // Create initial investor payments
        $this->command->info('Creating initial investor payments...');

        $investorPayments = [
            'cleargoal01@gmail.com' => 2000,
            'eugene.kaurov@gmail.com' => 1000,
        ];

        foreach ($investorPayments as $email => $amount) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->command->warn("User with email {$email} not found. Skipping...");
                continue;
            }

            $paymentData = [
                'user_id' => $user->id,
                'operation_id' => OperationType::FIRST->value, // First investment
                'amount' => $amount,
                'confirmed' => true,
                'created_at' => Carbon::now()->addMinutes(10), // After vehicle purchases
            ];

            $paymentService->createPayment($paymentData);

            $this->command->info("Created FIRST investment for {$user->name} ({$email}): \${$amount}");
        }

        $this->command->info('PaymentSeeder completed successfully!');
    }
}
