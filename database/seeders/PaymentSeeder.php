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

        // Calculate total vehicle cost for operator's initial contribution
        $totalVehicleCost = $vehicles->sum('cost');
        
        // Create operator's initial contribution (all vehicles as one contribution)
        $operatorContributionData = [
            'user_id' => $operatorUser->id,
            'operation_id' => OperationType::FIRST->value, // Operator's initial contribution
            'amount' => $totalVehicleCost,
            'confirmed' => true,
            'created_at' => $vehicles->min('created_at') ?? Carbon::now(),
        ];

        $paymentService->createPayment($operatorContributionData);
        $this->command->info("Created operator's initial contribution: \${$totalVehicleCost} (representing {$vehicles->count()} vehicles)");
        
        // Note: Individual BUY_CAR records are not created during seeding to avoid double-counting
        // The operator's contribution already represents the vehicle value
        // Individual BUY_CAR records will be created when vehicles are sold and repurchased

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
