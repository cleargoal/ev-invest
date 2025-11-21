<?php

namespace Database\Seeders;

use App\Enums\OperationType;
use App\Models\Payment;
use App\Services\TotalService;
use Illuminate\Database\Seeder;

class TotalSeeder extends Seeder
{
    /**
     * Create totals based on all confirmed payments
     * This simulates the running balance after all payments
     */
    public function run(): void
    {
        $totalService = app(TotalService::class);
        
        // Get all confirmed payments ordered by creation date
        $payments = Payment::where('confirmed', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
            
        if ($payments->isEmpty()) {
            $this->command->error('No confirmed payments found. Please run PaymentSeeder first.');
            return;
        }

        $this->command->info("Creating totals for {$payments->count()} payments...");

        // Create totals for each payment in chronological order
        foreach ($payments as $payment) {
            $totalService->createTotal($payment);
            
            $operationType = OperationType::from($payment->operation_id);
            $this->command->info("Created total for Payment #{$payment->id}: {$operationType->label()} \${$payment->amount}");
        }

        // Show final total
        $finalTotal = \App\Models\Total::orderBy('id', 'desc')->first();
        if ($finalTotal) {
            $this->command->info("Final pool total: \${$finalTotal->amount}");
        }

        $this->command->info('TotalSeeder completed successfully!');
    }
}
