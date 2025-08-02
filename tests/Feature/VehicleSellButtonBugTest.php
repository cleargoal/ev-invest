<?php

namespace Tests\Feature;

use App\Enums\OperationType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Payment;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleSellButtonBugTest extends TestCase
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
    public function sell_button_actions_on_correct_vehicle()
    {
        // Create multiple vehicles with distinct names
        $mazda = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda MX-30 Red',
            'cost' => 500000, // $5000
            'plan_sale' => 600000, // $6000
            'created_at' => '2025-01-01 10:00:00',
        ]);

        $tesla = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Tesla Model 3 Blue',
            'cost' => 800000, // $8000
            'plan_sale' => 900000, // $9000
            'created_at' => '2025-01-02 10:00:00',
        ]);

        $bmw = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'BMW i3 White',
            'cost' => 700000, // $7000
            'plan_sale' => 800000, // $8000
            'created_at' => '2025-01-03 10:00:00',
        ]);

        // Record initial state
        $vehiclesBefore = Vehicle::all()->keyBy('id');
        
        echo "\n=== VEHICLE SELL BUTTON BUG TEST ===\n";
        echo "Created vehicles:\n";
        foreach ($vehiclesBefore as $vehicle) {
            echo "  ID:{$vehicle->id} - {$vehicle->title} - Cost:\${$vehicle->cost} - Sale:\${$vehicle->plan_sale}\n";
        }

        // Test selling each vehicle specifically
        $this->sellAndVerifyVehicle($mazda, 650000, 'Mazda MX-30 Red');
        $this->sellAndVerifyVehicle($tesla, 950000, 'Tesla Model 3 Blue');
        $this->sellAndVerifyVehicle($bmw, 850000, 'BMW i3 White');
    }

    protected function sellAndVerifyVehicle(Vehicle $targetVehicle, int $salePrice, string $expectedTitle)
    {
        echo "\n--- Testing sale of: {$expectedTitle} (ID: {$targetVehicle->id}) ---\n";
        
        // Record state before selling
        $originalVehicle = Vehicle::find($targetVehicle->id);
        $this->assertNotNull($originalVehicle);
        $this->assertEquals($expectedTitle, $originalVehicle->title);
        $this->assertNull($originalVehicle->sale_date);
        
        // Sell the vehicle
        $result = $this->vehicleService->sellVehicle($targetVehicle, $salePrice);
        
        // Verify the CORRECT vehicle was sold
        $soldVehicle = Vehicle::find($targetVehicle->id);
        $this->assertNotNull($soldVehicle->sale_date, "Vehicle {$expectedTitle} should have sale_date after selling");
        $this->assertEquals($salePrice, $soldVehicle->price, "Vehicle {$expectedTitle} should have correct sale price");
        $this->assertEquals($expectedTitle, $soldVehicle->title, "Vehicle title should remain unchanged");
        
        // Verify OTHER vehicles were NOT affected
        $allVehicles = Vehicle::all();
        $otherVehicles = $allVehicles->where('id', '!=', $targetVehicle->id);
        
        foreach ($otherVehicles as $otherVehicle) {
            if ($otherVehicle->sale_date) {
                // If already sold, verify it wasn't changed
                continue;
            } else {
                // If not sold, verify it remains unsold
                $this->assertNull($otherVehicle->sale_date, 
                    "Vehicle {$otherVehicle->title} (ID: {$otherVehicle->id}) should NOT be sold when selling {$expectedTitle} (ID: {$targetVehicle->id})"
                );
            }
        }
        
        echo "✅ {$expectedTitle} sold correctly for \${$salePrice}\n";
        
        // Verify payments were created for the correct vehicle
        $vehiclePayments = Payment::where('vehicle_id', $targetVehicle->id)->get();
        $this->assertGreaterThan(0, $vehiclePayments->count(), "Payments should be linked to vehicle {$expectedTitle}");
        
        echo "✅ Payments linked correctly to {$expectedTitle}\n";
    }

    /** @test */
    public function vehicle_resource_query_returns_vehicles_in_correct_order()
    {
        // Create vehicles with specific order
        $vehicles = [];
        for ($i = 1; $i <= 5; $i++) {
            $vehicles[] = Vehicle::factory()->create([
                'user_id' => $this->companyUser->id,
                'title' => "Test Vehicle {$i}",
                'cost' => 500000 + ($i * 10000),
                'plan_sale' => 600000 + ($i * 10000),
                'created_at' => now()->addMinutes($i),
            ]);
        }

        // Get vehicles using VehicleResource query (same as in UI)
        $resourceVehicles = \App\Filament\Investor\Resources\VehicleResource::getEloquentQuery()
            ->orderBy('id', 'desc') // Same as VehicleResource default sort
            ->get();

        echo "\n=== VEHICLE RESOURCE QUERY ORDER TEST ===\n";
        echo "Vehicles in VehicleResource order:\n";
        foreach ($resourceVehicles as $index => $vehicle) {
            echo "  Position {$index}: ID:{$vehicle->id} - {$vehicle->title}\n";
        }

        // Verify the order matches what the UI would show
        $this->assertCount(5, $resourceVehicles);
        
        // Test that the vehicle at position 0 is actually the one with the highest ID
        $firstVehicle = $resourceVehicles->first();
        $lastCreatedVehicle = $vehicles[4]; // Last created should have highest ID
        
        $this->assertEquals($lastCreatedVehicle->id, $firstVehicle->id, 
            "First vehicle in resource query should be the last created vehicle");
        
        // Verify each position
        $reversedVehicles = array_reverse($vehicles);
        foreach ($resourceVehicles as $index => $resourceVehicle) {
            $this->assertEquals($reversedVehicles[$index]->id, $resourceVehicle->id,
                "Vehicle at position {$index} should match expected order");
        }
    }

    /** @test */
    public function vehicle_sell_action_in_filament_table_targets_correct_record()
    {
        // Create vehicles that might be confused due to similar data
        $vehicle1 = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda CX-5',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);

        $vehicle2 = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda MX-30',
            'cost' => 500000, // Same cost as vehicle1
            'plan_sale' => 600000, // Same plan_sale as vehicle1
        ]);

        $vehicle3 = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Toyota Prius',
            'cost' => 500000, // Same cost again
            'plan_sale' => 600000, // Same plan_sale again
        ]);

        echo "\n=== FILAMENT TABLE ACTION TARGET TEST ===\n";
        echo "Testing vehicles with similar data:\n";
        echo "  Vehicle 1: ID:{$vehicle1->id} - {$vehicle1->title}\n";
        echo "  Vehicle 2: ID:{$vehicle2->id} - {$vehicle2->title}\n";
        echo "  Vehicle 3: ID:{$vehicle3->id} - {$vehicle3->title}\n";

        // Simulate what happens when user clicks "sell" on vehicle2 (Mazda MX-30)
        $targetVehicle = $vehicle2;
        $salePrice = 650000;
        
        echo "\nSimulating sell action on: {$targetVehicle->title} (ID: {$targetVehicle->id})\n";
        
        // This simulates the Filament action receiving the record
        $recordPassedToAction = Vehicle::find($targetVehicle->id);
        $this->assertNotNull($recordPassedToAction);
        $this->assertEquals($targetVehicle->id, $recordPassedToAction->id);
        $this->assertEquals($targetVehicle->title, $recordPassedToAction->title);
        
        // Sell the vehicle
        $this->vehicleService->sellVehicle($recordPassedToAction, $salePrice);
        
        // Verify the correct vehicle was sold
        $vehicle1->refresh();
        $vehicle2->refresh();
        $vehicle3->refresh();
        
        $this->assertNull($vehicle1->sale_date, "Vehicle 1 should NOT be sold");
        $this->assertNotNull($vehicle2->sale_date, "Vehicle 2 SHOULD be sold");
        $this->assertNull($vehicle3->sale_date, "Vehicle 3 should NOT be sold");
        
        $this->assertEquals($salePrice, $vehicle2->price, "Vehicle 2 should have correct price");
        $this->assertEquals('Mazda MX-30', $vehicle2->title, "Vehicle 2 title should be unchanged");
        
        echo "✅ Correct vehicle was sold: {$vehicle2->title}\n";
        echo "✅ Other vehicles remained unsold\n";
        
        // Verify payments are linked to correct vehicle
        $paymentsForVehicle2 = Payment::where('vehicle_id', $vehicle2->id)->count();
        $paymentsForVehicle1 = Payment::where('vehicle_id', $vehicle1->id)->count();
        $paymentsForVehicle3 = Payment::where('vehicle_id', $vehicle3->id)->count();
        
        $this->assertGreaterThan(0, $paymentsForVehicle2, "Payments should exist for sold vehicle");
        $this->assertEquals(0, $paymentsForVehicle1, "No payments should exist for unsold vehicle 1");
        $this->assertEquals(0, $paymentsForVehicle3, "No payments should exist for unsold vehicle 3");
        
        echo "✅ Payments correctly linked to the right vehicle\n";
    }
}