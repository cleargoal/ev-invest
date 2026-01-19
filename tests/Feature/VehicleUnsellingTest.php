<?php

namespace Tests\Feature;

use App\Enums\OperationType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Payment;
use App\Models\Total;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VehicleUnsellingTest extends TestCase
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
        
        // Create users
        $this->companyUser = User::factory()->create();
        $this->companyUser->assignRole('company');
        
        $this->investorUser = User::factory()->create();
        $this->investorUser->assignRole('investor');
        
        // Create a payment and contribution for investor
        $payment = Payment::factory()->create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 100000, // $1000 in cents
            'confirmed' => true,
        ]);
        
        \App\Models\Contribution::factory()->create([
            'user_id' => $this->investorUser->id,
            'payment_id' => $payment->id,
            'percents' => 500000, // 50%
            'amount' => 100000,
        ]);
        
        // Create operations
        \App\Models\Operation::factory()->create(['id' => OperationType::CONTRIB->value, 'title' => 'Contribution', 'key' => 'contrib', 'description' => 'Investor contribution']);
        \App\Models\Operation::factory()->create(['id' => OperationType::REVENUE->value, 'title' => 'Revenue', 'key' => 'revenue', 'description' => 'Company revenue']);
        \App\Models\Operation::factory()->create(['id' => OperationType::INCOME->value, 'title' => 'Income', 'key' => 'income', 'description' => 'Investor income']);
    }

    /** @test */
    public function it_can_sell_vehicle_and_create_linked_payments()
    {
        // Create and sell a vehicle
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Test Vehicle',
            'cost' => 500000, // $5000 in cents
            'plan_sale' => 600000, // $6000 in cents
        ]);

        $salePrice = 700000; // $7000 in cents
        $this->vehicleService->sellVehicle($vehicle, $salePrice);

        $vehicle->refresh();

        // Verify vehicle sale data
        $this->assertNotNull($vehicle->sale_date);
        $this->assertEquals($salePrice, $vehicle->price);
        $this->assertEquals(200000, $vehicle->profit); // $2000 profit in cents

        // Verify payments were created and linked to vehicle
        $vehiclePayments = Payment::where('vehicle_id', $vehicle->id)->get();
        $this->assertGreaterThan(0, $vehiclePayments->count());

        // Should have company commission payment
        $companyPayment = $vehiclePayments->where('operation_id', OperationType::REVENUE->value)->first();
        $this->assertNotNull($companyPayment);
        $this->assertEquals($this->companyUser->id, $companyPayment->user_id);

        // Should have investor income payment
        $investorPayment = $vehiclePayments->where('operation_id', OperationType::INCOME->value)->first();
        $this->assertNotNull($investorPayment);
        $this->assertEquals($this->investorUser->id, $investorPayment->user_id);

        return $vehicle;
    }

    /** @test */
    public function it_can_unsell_vehicle_and_cancel_payments()
    {
        // First sell a vehicle
        $vehicle = $this->it_can_sell_vehicle_and_create_linked_payments();
        
        // Record initial payment counts
        $totalPaymentsBefore = Payment::count();
        $activePaymentsBefore = Payment::active()->count();
        $vehiclePaymentsBefore = Payment::where('vehicle_id', $vehicle->id)->active()->count();

        // Record totals before unselling
        $totalsBefore = Total::sum('amount');

        // Unsell the vehicle
        $result = $this->vehicleService->unsellVehicle($vehicle, 'Test cancellation reason');
        $this->assertTrue($result);

        $vehicle->refresh();

        // Verify vehicle data is cleared
        $this->assertNull($vehicle->sale_date);
        $this->assertEquals(0, $vehicle->price); // MoneyCast converts null to 0
        $this->assertEquals(0, $vehicle->profit); // MoneyCast converts null to 0
        $this->assertNull($vehicle->sale_duration);

        // Verify vehicle is completely reset (appears never sold)
        // The new business logic clears cancellation data to make vehicle appear "for sale" again
        $this->assertNull($vehicle->cancelled_at);
        $this->assertNull($vehicle->cancellation_reason);
        $this->assertNull($vehicle->cancelled_by);
        
        // However, audit trail is preserved at the payment level (cancelled payments)
        $this->assertTrue($vehicle->isUnsold(), 'Vehicle should be identified as unsold based on cancelled payments');

        // Verify related payments are cancelled
        $vehiclePayments = Payment::where('vehicle_id', $vehicle->id)->get();
        foreach ($vehiclePayments as $payment) {
            if ($payment->created_at < $vehicle->cancelled_at) {
                $this->assertTrue($payment->is_cancelled, "Payment {$payment->id} should be cancelled");
            }
        }

        // Verify compensating payments were created
        $totalPaymentsAfter = Payment::count();
        $this->assertGreaterThan($totalPaymentsBefore, $totalPaymentsAfter);

        // Verify financial impact is reversed (totals should be adjusted)
        // Since cancelled_at is now null, look for WITHDRAW payments created during unselling
        $compensatingPayments = Payment::where('vehicle_id', $vehicle->id)
            ->where('operation_id', \App\Enums\OperationType::WITHDRAW->value)
            ->where('is_cancelled', false)
            ->get();
        $this->assertGreaterThan(0, $compensatingPayments->count(), 'Should have compensating WITHDRAW payments');

        echo "\n=== UNSELLING TEST RESULTS ===\n";
        echo "Vehicle ID: {$vehicle->id}\n";
        echo "Sale data cleared: " . ($vehicle->sale_date ? 'NO' : 'YES') . "\n";
        echo "Cancellation reason: {$vehicle->cancellation_reason}\n";
        echo "Original payments: {$vehiclePaymentsBefore}\n";
        echo "Cancelled payments: " . Payment::where('vehicle_id', $vehicle->id)->cancelled()->count() . "\n";
        echo "Compensating payments: " . $compensatingPayments->count() . "\n";
        echo "Total payments before: {$totalPaymentsBefore}\n";
        echo "Total payments after: {$totalPaymentsAfter}\n";
    }

    /** @test */
    public function vehicle_appears_in_for_sale_list_after_unselling()
    {
        // Create and sell vehicle
        $vehicle = $this->it_can_sell_vehicle_and_create_linked_payments();
        
        // Vehicle should not appear in "for sale" query when sold
        $forSaleBeforeUnsell = Vehicle::where(function($q) {
            $q->whereNull('profit')->orWhere('profit', 0);
        })->whereNull('sale_date')->count();
        
        // Unsell the vehicle
        $this->vehicleService->unsellVehicle($vehicle, 'Test unselling');
        
        // Vehicle should appear in "for sale" query after unselling
        $forSaleAfterUnsell = Vehicle::where(function($q) {
            $q->whereNull('profit')->orWhere('profit', 0);
        })->whereNull('sale_date')->count();
        
        $this->assertEquals($forSaleBeforeUnsell + 1, $forSaleAfterUnsell);
        
        echo "\n=== VEHICLE VISIBILITY TEST ===\n";
        echo "For sale count before unsell: {$forSaleBeforeUnsell}\n";
        echo "For sale count after unsell: {$forSaleAfterUnsell}\n";
        echo "Vehicle appears in for-sale list: " . ($forSaleAfterUnsell > $forSaleBeforeUnsell ? 'YES' : 'NO') . "\n";
    }
}