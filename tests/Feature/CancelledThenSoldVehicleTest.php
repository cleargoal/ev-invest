<?php

namespace Tests\Feature;

use App\Enums\OperationType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Payment;
use App\Services\VehicleService;
use App\Services\VehicleCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelledThenSoldVehicleTest extends TestCase
{
    use RefreshDatabase;

    protected VehicleService $vehicleService;
    protected VehicleCancellationService $cancellationService;
    protected User $companyUser;
    protected User $investorUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vehicleService = app(VehicleService::class);
        $this->cancellationService = app(VehicleCancellationService::class);
        
        // Create roles
        
        // Create users
        $this->companyUser = User::factory()->create(['name' => 'Company User']);
        $this->companyUser->assignRole('company');
        
        $this->investorUser = User::factory()->create(['name' => 'Investor User']);
        $this->investorUser->assignRole('investor');
        
        // Create contribution for investor
        $payment = Payment::factory()->create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 100000,
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
    public function cancelled_then_sold_vehicle_should_appear_in_sold_widget()
    {
        // Create a vehicle
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda MX-30 Red',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);

        echo "\n=== CANCELLED THEN SOLD VEHICLE TEST ===\n";
        echo "Original vehicle state:\n";
        echo "  ID: {$vehicle->id}\n";
        echo "  Title: {$vehicle->title}\n";
        echo "  sale_date: {$vehicle->sale_date}\n";
        echo "  cancelled_at: {$vehicle->cancelled_at}\n";

        // Step 1: Sell the vehicle
        $this->vehicleService->sellVehicle($vehicle, 650000);
        $vehicle->refresh();
        
        echo "\nAfter first sale:\n";
        echo "  sale_date: {$vehicle->sale_date}\n";
        echo "  cancelled_at: {$vehicle->cancelled_at}\n";
        echo "  price: \${$vehicle->price}\n";

        // Step 2: Cancel the sale (but keep sale data)
        $this->cancellationService->cancelVehicleSale($vehicle, 'Customer changed mind', $this->companyUser);
        $vehicle->refresh();
        
        echo "\nAfter cancellation:\n";
        echo "  sale_date: {$vehicle->sale_date}\n";
        echo "  cancelled_at: {$vehicle->cancelled_at}\n";
        echo "  price: \${$vehicle->price}\n";
        echo "  isCancelled(): " . ($vehicle->isCancelled() ? 'YES' : 'NO') . "\n";

        // Step 3: Sell the vehicle again (with a slight delay to ensure different timestamp)
        sleep(1);
        $this->vehicleService->sellVehicle($vehicle, 680000);
        $vehicle->refresh();
        
        echo "\nAfter second sale:\n";
        echo "  sale_date: {$vehicle->sale_date}\n";
        echo "  cancelled_at: {$vehicle->cancelled_at}\n";
        echo "  price: \${$vehicle->price}\n";
        echo "  isSold(): " . ($vehicle->isSold() ? 'YES' : 'NO') . "\n";
        echo "  isCancelled(): " . ($vehicle->isCancelled() ? 'YES' : 'NO') . "\n";

        // Check current sold() scope behavior
        $soldVehicles = Vehicle::sold()->get();
        echo "\nVehicles in sold() scope:\n";
        foreach ($soldVehicles as $soldVehicle) {
            echo "  ID: {$soldVehicle->id} - {$soldVehicle->title}\n";
        }

        // This should pass but currently fails due to the bug
        $this->assertTrue($vehicle->isSold(), 'Vehicle should be considered sold');
        $this->assertFalse($vehicle->isCancelled(), 'Vehicle should not be considered cancelled');
        
        // The bug: vehicle doesn't appear in sold widget because cancelled_at is not null
        $soldVehicleIds = Vehicle::sold()->pluck('id')->toArray();
        $this->assertContains($vehicle->id, $soldVehicleIds, 
            'Previously cancelled but now sold vehicle should appear in sold vehicles widget');

        echo "\n✅ Vehicle appears in sold widget despite previous cancellation\n";
    }

    /** @test */
    public function unsold_then_sold_vehicle_should_appear_in_sold_widget()
    {
        // Test the unsell scenario (sale data cleared, then sold again)
        
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Tesla Model 3 Blue',
            'cost' => 800000,
            'plan_sale' => 900000,
        ]);

        echo "\n=== UNSOLD THEN SOLD VEHICLE TEST ===\n";
        
        // Step 1: Sell the vehicle
        $this->vehicleService->sellVehicle($vehicle, 950000);
        $vehicle->refresh();
        
        echo "After first sale: sale_date={$vehicle->sale_date}, cancelled_at={$vehicle->cancelled_at}\n";

        // Step 2: Unsell the vehicle (clears sale data)
        $this->vehicleService->unsellVehicle($vehicle, 'Clear sale data');
        $vehicle->refresh();
        
        echo "After unselling: sale_date={$vehicle->sale_date}, cancelled_at={$vehicle->cancelled_at}\n";
        echo "isUnsold(): " . ($vehicle->isUnsold() ? 'YES' : 'NO') . "\n";

        // Step 3: Sell the vehicle again (with a slight delay to ensure different timestamp)
        sleep(1);
        $this->vehicleService->sellVehicle($vehicle, 980000);
        $vehicle->refresh();
        
        echo "After second sale: sale_date={$vehicle->sale_date}, cancelled_at={$vehicle->cancelled_at}\n";
        echo "isSold(): " . ($vehicle->isSold() ? 'YES' : 'NO') . "\n";

        // Check sold() scope behavior
        $soldVehicles = Vehicle::sold()->get();
        echo "\nVehicles in sold() scope:\n";
        foreach ($soldVehicles as $soldVehicle) {
            echo "  ID: {$soldVehicle->id} - {$soldVehicle->title}\n";
        }

        // This should pass but currently fails due to the bug
        $this->assertTrue($vehicle->isSold(), 'Vehicle should be considered sold');
        
        // The bug: vehicle doesn't appear in sold widget because cancelled_at is not null
        $soldVehicleIds = Vehicle::sold()->pluck('id')->toArray();
        $this->assertContains($vehicle->id, $soldVehicleIds, 
            'Previously unsold but now sold vehicle should appear in sold vehicles widget');

        echo "\n✅ Vehicle appears in sold widget despite previous unselling\n";
    }
}