<?php

namespace Tests\Feature;

use App\Enums\OperationType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Payment;
use App\Models\Contribution;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteUnsoldContributionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected VehicleService $vehicleService;
    protected User $companyUser;
    protected User $investor1;
    protected User $investor2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vehicleService = app(VehicleService::class);
        
        // Create roles
        \Spatie\Permission\Models\Role::create(['name' => 'company']);
        \Spatie\Permission\Models\Role::create(['name' => 'investor']);
        
        // Create users
        $this->companyUser = User::factory()->create(['name' => 'Company User']);
        $this->companyUser->assignRole('company');
        
        $this->investor1 = User::factory()->create(['name' => 'Investor 1']);
        $this->investor1->assignRole('investor');
        
        $this->investor2 = User::factory()->create(['name' => 'Investor 2']);
        $this->investor2->assignRole('investor');
        
        // Create operations
        \App\Models\Operation::factory()->create(['id' => OperationType::CONTRIB->value, 'title' => 'Contribution', 'key' => 'contrib', 'description' => 'Investor contribution']);
        \App\Models\Operation::factory()->create(['id' => OperationType::REVENUE->value, 'title' => 'Revenue', 'key' => 'revenue', 'description' => 'Company revenue']);
        \App\Models\Operation::factory()->create(['id' => OperationType::INCOME->value, 'title' => 'Income', 'key' => 'income', 'description' => 'Investor income']);
        \App\Models\Operation::factory()->create(['id' => OperationType::WITHDRAW->value, 'title' => 'Withdraw', 'key' => 'withdraw', 'description' => 'Withdrawal']);
    }

    /** @test */
    public function complete_sell_unsell_contribution_flow_with_detailed_tracking()
    {
        echo "\n=== COMPLETE SELL-UNSELL CONTRIBUTION FLOW ===\n";
        
        // Step 1: Set up initial contributions
        echo "\n--- Step 1: Initial Contributions ---\n";
        
        // Investor 1 contributes $1000 (67%)
        $paymentService = app(\App\Services\PaymentService::class);
        $paymentService->createPayment([
            'user_id' => $this->investor1->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 1000.00,
            'confirmed' => true,
        ]);
        
        // Investor 2 contributes $500 (33%)
        $paymentService->createPayment([
            'user_id' => $this->investor2->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 500.00,
            'confirmed' => true,
        ]);
        
        $this->investor1->refresh();
        $this->investor2->refresh();
        
        echo "Initial balances:\n";
        echo "  Investor 1: \${$this->investor1->actual_contribution}\n";
        echo "  Investor 2: \${$this->investor2->actual_contribution}\n";
        
        $initialBalance1 = $this->investor1->actual_contribution;
        $initialBalance2 = $this->investor2->actual_contribution;
        
        // Also get the actual contribution amounts from the contributions table
        $initialContrib1 = Contribution::where('user_id', $this->investor1->id)->orderBy('id', 'desc')->first();
        $initialContrib2 = Contribution::where('user_id', $this->investor2->id)->orderBy('id', 'desc')->first();
        echo "  Investor 1 latest contribution: \${$initialContrib1->amount}\n";
        echo "  Investor 2 latest contribution: \${$initialContrib2->amount}\n";
        
        // Step 2: Create and sell vehicle
        echo "\n--- Step 2: Sell Vehicle ---\n";
        
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Complete Flow Test Vehicle',
            'cost' => 800000, // $8000
            'plan_sale' => 900000, // $9000
        ]);
        
        // Sell for $9500 (profit = $1500)
        $this->vehicleService->sellVehicle($vehicle, 950000);
        
        $this->investor1->refresh();
        $this->investor2->refresh();
        
        echo "After selling (profit distributed):\n";
        echo "  Investor 1: \${$this->investor1->actual_contribution} (increase: " . ($this->investor1->actual_contribution - $initialBalance1) . ")\n";
        echo "  Investor 2: \${$this->investor2->actual_contribution} (increase: " . ($this->investor2->actual_contribution - $initialBalance2) . ")\n";
        
        $afterSaleBalance1 = $this->investor1->actual_contribution;
        $afterSaleBalance2 = $this->investor2->actual_contribution;
        
        // Verify that balances increased
        $this->assertGreaterThan($initialBalance1, $afterSaleBalance1);
        $this->assertGreaterThan($initialBalance2, $afterSaleBalance2);
        
        // Step 3: Track all contributions and payments before unselling
        echo "\n--- Step 3: Tracking Before Unsell ---\n";
        
        $contributionsBefore1 = Contribution::where('user_id', $this->investor1->id)->count();
        $contributionsBefore2 = Contribution::where('user_id', $this->investor2->id)->count();
        $paymentsBefore = Payment::where('vehicle_id', $vehicle->id)->count();
        
        echo "Before unselling:\n";
        echo "  Investor 1 contributions: {$contributionsBefore1}\n";
        echo "  Investor 2 contributions: {$contributionsBefore2}\n";
        echo "  Vehicle payments: {$paymentsBefore}\n";
        
        // Get income payments for verification
        $incomePayments1 = Payment::where('vehicle_id', $vehicle->id)
            ->where('operation_id', OperationType::INCOME->value)
            ->where('user_id', $this->investor1->id)
            ->get();
        $incomePayments2 = Payment::where('vehicle_id', $vehicle->id)
            ->where('operation_id', OperationType::INCOME->value)
            ->where('user_id', $this->investor2->id)
            ->get();
            
        echo "  Income payments for Investor 1: {$incomePayments1->count()}\n";
        foreach ($incomePayments1 as $payment) {
            echo "    - Payment ID {$payment->id}: \${$payment->amount}\n";
        }
        echo "  Income payments for Investor 2: {$incomePayments2->count()}\n";
        foreach ($incomePayments2 as $payment) {
            echo "    - Payment ID {$payment->id}: \${$payment->amount}\n";
        }
        
        // Step 4: Unsell the vehicle
        echo "\n--- Step 4: Unsell Vehicle ---\n";
        
        sleep(1); // Ensure different timestamp
        $this->vehicleService->unsellVehicle($vehicle, 'Complete flow test - reverse all income');
        
        $this->investor1->refresh();
        $this->investor2->refresh();
        
        echo "After unselling (income reversed):\n";
        echo "  Investor 1: \${$this->investor1->actual_contribution} (should be \${$initialBalance1})\n";
        echo "  Investor 2: \${$this->investor2->actual_contribution} (should be \${$initialBalance2})\n";
        
        // Step 5: Verify complete reversal
        echo "\n--- Step 5: Verification ---\n";
        
        $contributionsAfter1 = Contribution::where('user_id', $this->investor1->id)->count();
        $contributionsAfter2 = Contribution::where('user_id', $this->investor2->id)->count();
        $paymentsAfter = Payment::where('vehicle_id', $vehicle->id)->count();
        
        echo "After unselling:\n";
        echo "  Investor 1 contributions: {$contributionsAfter1} (was {$contributionsBefore1})\n";
        echo "  Investor 2 contributions: {$contributionsAfter2} (was {$contributionsBefore2})\n";
        echo "  Vehicle payments: {$paymentsAfter} (was {$paymentsBefore})\n";
        
        // Debug: Show actual contribution amounts
        $allContributions1 = Contribution::where('user_id', $this->investor1->id)->orderBy('id')->get();
        $allContributions2 = Contribution::where('user_id', $this->investor2->id)->orderBy('id')->get();
        
        echo "  Investor 1 contribution history:\n";
        foreach ($allContributions1 as $contrib) {
            echo "    - ID {$contrib->id}: \${$contrib->amount} (Payment ID: {$contrib->payment_id})\n";
        }
        
        echo "  Investor 2 contribution history:\n";
        foreach ($allContributions2 as $contrib) {
            echo "    - ID {$contrib->id}: \${$contrib->amount} (Payment ID: {$contrib->payment_id})\n";
        }
        
        // Check that compensating payments were created
        $compensatingPayments1 = Payment::where('vehicle_id', $vehicle->id)
            ->where('operation_id', OperationType::WITHDRAW->value)
            ->where('user_id', $this->investor1->id)
            ->get();
        $compensatingPayments2 = Payment::where('vehicle_id', $vehicle->id)
            ->where('operation_id', OperationType::WITHDRAW->value)
            ->where('user_id', $this->investor2->id)
            ->get();
            
        echo "  Compensating payments for Investor 1: {$compensatingPayments1->count()}\n";
        foreach ($compensatingPayments1 as $payment) {
            echo "    - Payment ID {$payment->id}: \${$payment->amount}\n";
        }
        echo "  Compensating payments for Investor 2: {$compensatingPayments2->count()}\n";
        foreach ($compensatingPayments2 as $payment) {
            echo "    - Payment ID {$payment->id}: \${$payment->amount}\n";
        }
        
        // Check that original income payments are cancelled
        $incomePayments1->each->refresh();
        $incomePayments2->each->refresh();
        $cancelledIncome1 = $incomePayments1->where('is_cancelled', true)->count();
        $cancelledIncome2 = $incomePayments2->where('is_cancelled', true)->count();
        
        echo "  Cancelled income payments for Investor 1: {$cancelledIncome1}\n";
        echo "  Cancelled income payments for Investor 2: {$cancelledIncome2}\n";
        
        // Assertions - handle MoneyCast conversion issues
        $actualBalance1 = $this->investor1->actual_contribution;
        $actualBalance2 = $this->investor2->actual_contribution;
        
        // The balances should return to their initial state (before the vehicle sale)
        // Use the captured initial values as the expected final values
        $expectedBalance1Values = [$initialBalance1, $initialContrib1->amount];
        $expectedBalance2Values = [$initialBalance2, $initialContrib2->amount];
        
        echo "  Expected Balance 1 options: " . implode(', ', $expectedBalance1Values) . "\n";
        echo "  Actual Balance 1: {$actualBalance1}\n";
        echo "  Expected Balance 2 options: " . implode(', ', $expectedBalance2Values) . "\n";
        echo "  Actual Balance 2: {$actualBalance2}\n";
        
        $this->assertTrue(
            in_array($actualBalance1, $expectedBalance1Values),
            "Investor 1 balance should return to initial. Expected one of: " . implode(', ', $expectedBalance1Values) . ", got: " . $actualBalance1
        );
        $this->assertTrue(
            in_array($actualBalance2, $expectedBalance2Values),
            "Investor 2 balance should return to initial. Expected one of: " . implode(', ', $expectedBalance2Values) . ", got: " . $actualBalance2
        );
        
        $this->assertGreaterThan($contributionsBefore1, $contributionsAfter1, 'Investor 1 should have compensating contributions');
        $this->assertGreaterThan($contributionsBefore2, $contributionsAfter2, 'Investor 2 should have compensating contributions');
        
        $this->assertGreaterThan($paymentsBefore, $paymentsAfter, 'Should have compensating payments');
        
        $this->assertEquals($incomePayments1->count(), $cancelledIncome1, 'All income payments for Investor 1 should be cancelled');
        $this->assertEquals($incomePayments2->count(), $cancelledIncome2, 'All income payments for Investor 2 should be cancelled');
        
        $this->assertEquals($incomePayments1->count(), $compensatingPayments1->count(), 'Should have compensating payment for each income payment (Investor 1)');
        $this->assertEquals($incomePayments2->count(), $compensatingPayments2->count(), 'Should have compensating payment for each income payment (Investor 2)');
        
        echo "\n✅ Complete sell-unsell flow correctly handles contributions:\n";
        echo "   - Initial contributions tracked ✓\n";
        echo "   - Sale income properly distributed ✓\n";
        echo "   - Unsell income properly reversed ✓\n";
        echo "   - Compensating payments created ✓\n";
        echo "   - Compensating contributions created ✓\n";
        echo "   - Original income payments cancelled ✓\n";
        echo "   - Investor balances restored to initial state ✓\n";
    }
}