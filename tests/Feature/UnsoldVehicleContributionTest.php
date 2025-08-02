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

class UnsoldVehicleContributionTest extends TestCase
{
    use RefreshDatabase;

    protected VehicleService $vehicleService;
    protected User $companyUser;
    protected User $investorUser;

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
        
        $this->investorUser = User::factory()->create(['name' => 'Investor User']);
        $this->investorUser->assignRole('investor');
        
        // Create initial contribution for investor
        $payment = Payment::factory()->create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 100000,
            'confirmed' => true,
        ]);
        
        \App\Models\Contribution::factory()->create([
            'user_id' => $this->investorUser->id,
            'payment_id' => $payment->id,
            'percents' => 1000000, // 100% for simplicity
            'amount' => 100000,
        ]);
        
        // Update user's actual contribution
        $this->investorUser->update(['actual_contribution' => 100000]);
        
        // Create operations
        \App\Models\Operation::factory()->create(['id' => OperationType::CONTRIB->value, 'title' => 'Contribution', 'key' => 'contrib', 'description' => 'Investor contribution']);
        \App\Models\Operation::factory()->create(['id' => OperationType::REVENUE->value, 'title' => 'Revenue', 'key' => 'revenue', 'description' => 'Company revenue']);
        \App\Models\Operation::factory()->create(['id' => OperationType::INCOME->value, 'title' => 'Income', 'key' => 'income', 'description' => 'Investor income']);
    }

    /** @test */
    public function unselling_vehicle_should_reverse_investor_income_contributions()
    {
        echo "\n=== UNSOLD VEHICLE CONTRIBUTION TEST ===\n";
        
        // Create a vehicle
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Test Vehicle for Unselling',
            'cost' => 500000, // $5000
            'plan_sale' => 600000, // $6000
        ]);

        // Check initial state
        $initialContributions = Contribution::where('user_id', $this->investorUser->id)->count();
        $initialBalance = $this->investorUser->actual_contribution;
        
        echo "Initial state:\n";
        echo "  Investor contributions: {$initialContributions}\n";
        echo "  Investor balance: \${$initialBalance}\n";

        // Step 1: Sell the vehicle (this should create INCOME payments and contributions)
        $this->vehicleService->sellVehicle($vehicle, 650000); // $6500, profit = $1500
        $vehicle->refresh();
        
        // Check state after selling
        $afterSaleContributions = Contribution::where('user_id', $this->investorUser->id)->count();
        $this->investorUser->refresh();
        $afterSaleBalance = $this->investorUser->actual_contribution;
        
        echo "\nAfter selling vehicle:\n";
        echo "  Investor contributions: {$afterSaleContributions}\n";
        echo "  Investor balance: \${$afterSaleBalance}\n";
        
        // Find the INCOME payments created for this vehicle
        $incomePayments = Payment::where('vehicle_id', $vehicle->id)
            ->where('operation_id', OperationType::INCOME->value)
            ->where('user_id', $this->investorUser->id)
            ->get();
        
        echo "  INCOME payments created: {$incomePayments->count()}\n";
        foreach ($incomePayments as $payment) {
            echo "    Payment ID: {$payment->id}, Amount: \${$payment->amount}\n";
        }
        
        // Verify that selling created income and increased investor balance
        $this->assertGreaterThan($initialContributions, $afterSaleContributions, 'Selling should create additional contributions');
        $this->assertGreaterThan($initialBalance, $afterSaleBalance, 'Selling should increase investor balance');
        
        // Step 2: Unsell the vehicle (this should reverse the income)
        sleep(1); // Ensure different timestamp
        $this->vehicleService->unsellVehicle($vehicle, 'Test unselling - should reverse income');
        $vehicle->refresh();
        
        // Check state after unselling
        $afterUnsellContributions = Contribution::where('user_id', $this->investorUser->id)->count();
        $this->investorUser->refresh();
        $afterUnsellBalance = $this->investorUser->actual_contribution;
        
        echo "\nAfter unselling vehicle:\n";
        echo "  Investor contributions: {$afterUnsellContributions}\n";
        echo "  Investor balance: \${$afterUnsellBalance}\n";
        
        // Check if INCOME payments were cancelled
        $incomePayments->each->refresh();
        $cancelledPayments = $incomePayments->where('is_cancelled', true)->count();
        echo "  Cancelled INCOME payments: {$cancelledPayments} / {$incomePayments->count()}\n";
        
        // THE BUG: Unselling should create compensating contributions to reverse the income
        // This test will initially fail, showing the bug
        $this->assertGreaterThan($afterSaleContributions, $afterUnsellContributions, 
            'Unselling should create compensating contributions to reverse income');
        
        $this->assertEquals($initialBalance, $afterUnsellBalance, 
            'Investor balance should return to initial state after unselling');
            
        echo "\n✅ Unselling correctly reversed investor income contributions\n";
    }

    /** @test */
    public function multiple_investors_income_is_reversed_when_unselling()
    {
        echo "\n=== MULTIPLE INVESTORS UNSELL TEST ===\n";
        
        // Create second investor
        $secondInvestor = User::factory()->create(['name' => 'Second Investor']);
        $secondInvestor->assignRole('investor');
        
        // Create contribution for second investor
        $payment2 = Payment::factory()->create([
            'user_id' => $secondInvestor->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 50000, // $500
            'confirmed' => true,
        ]);
        
        \App\Models\Contribution::factory()->create([
            'user_id' => $secondInvestor->id,
            'payment_id' => $payment2->id,
            'percents' => 333333, // ~33%
            'amount' => 50000,
        ]);
        
        $secondInvestor->update(['actual_contribution' => 50000]);
        
        // Update first investor's percentage to ~67% (100000 / 150000)
        $lastContrib = Contribution::where('user_id', $this->investorUser->id)->latest()->first();
        $lastContrib->update(['percents' => 666667]);
        
        echo "Setup: Two investors with different contribution percentages\n";
        echo "  Investor 1: \${$this->investorUser->actual_contribution} (~67%)\n";
        echo "  Investor 2: \${$secondInvestor->actual_contribution} (~33%)\n";
        
        // Create vehicle and sell it
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Multi-Investor Test Vehicle',
            'cost' => 800000, // $8000
            'plan_sale' => 900000, // $9000
        ]);
        
        // Record initial balances
        $initialBalance1 = $this->investorUser->actual_contribution;
        $initialBalance2 = $secondInvestor->actual_contribution;
        
        // Sell vehicle with profit
        $this->vehicleService->sellVehicle($vehicle, 950000); // $9500, profit = $1500
        
        // Check balances after selling
        $this->investorUser->refresh();
        $secondInvestor->refresh();
        $afterSaleBalance1 = $this->investorUser->actual_contribution;
        $afterSaleBalance2 = $secondInvestor->actual_contribution;
        
        echo "\nAfter selling:\n";
        echo "  Investor 1: \${$afterSaleBalance1} (was \${$initialBalance1})\n";
        echo "  Investor 2: \${$afterSaleBalance2} (was \${$initialBalance2})\n";
        
        // Both should have increased
        $this->assertGreaterThan($initialBalance1, $afterSaleBalance1);
        $this->assertGreaterThan($initialBalance2, $afterSaleBalance2);
        
        // Unsell the vehicle
        sleep(1); // Ensure different timestamp
        $this->vehicleService->unsellVehicle($vehicle, 'Multi-investor unsell test');
        
        // Check balances after unselling
        $this->investorUser->refresh();
        $secondInvestor->refresh();
        $afterUnsellBalance1 = $this->investorUser->actual_contribution;
        $afterUnsellBalance2 = $secondInvestor->actual_contribution;
        
        echo "\nAfter unselling:\n";
        echo "  Investor 1: \${$afterUnsellBalance1} (should be \${$initialBalance1})\n";
        echo "  Investor 2: \${$afterUnsellBalance2} (should be \${$initialBalance2})\n";
        
        // Both should return to initial balances
        $this->assertEquals($initialBalance1, $afterUnsellBalance1, 
            'Investor 1 balance should return to initial state');
        $this->assertEquals($initialBalance2, $afterUnsellBalance2, 
            'Investor 2 balance should return to initial state');
            
        echo "\n✅ Multiple investors' income correctly reversed\n";
    }

    /** @test */
    public function cancelled_income_payments_dont_affect_contribution_totals()
    {
        echo "\n=== CANCELLED PAYMENTS CONTRIBUTION TEST ===\n";
        
        // Create and sell vehicle
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Cancelled Payments Test Vehicle',
            'cost' => 600000,
            'plan_sale' => 700000,
        ]);
        
        $this->vehicleService->sellVehicle($vehicle, 750000);
        
        // Get the income payment
        $incomePayment = Payment::where('vehicle_id', $vehicle->id)
            ->where('operation_id', OperationType::INCOME->value)
            ->where('user_id', $this->investorUser->id)
            ->first();
        
        $this->assertNotNull($incomePayment, 'Income payment should be created');
        echo "Income payment created: ID {$incomePayment->id}, Amount: \${$incomePayment->amount}\n";
        
        // Unsell vehicle (which cancels the payment)
        $this->vehicleService->unsellVehicle($vehicle, 'Test payment cancellation');
        
        // Check that payment is cancelled
        $incomePayment->refresh();
        $this->assertTrue($incomePayment->is_cancelled, 'Income payment should be cancelled');
        echo "Payment cancelled: {$incomePayment->is_cancelled}\n";
        
        // Verify that cancelled payments don't count in future contribution calculations
        $this->investorUser->refresh();
        $finalBalance = $this->investorUser->actual_contribution;
        
        echo "Final investor balance: \${$finalBalance}\n";
        
        // The balance should reflect that the cancelled income is not counted
        $this->assertEquals(100000, $finalBalance, 
            'Cancelled income should not be included in final contribution balance');
            
        echo "✅ Cancelled payments correctly excluded from contribution totals\n";
    }
}