<?php

namespace Tests\Feature;

use App\Enums\OperationType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Payment;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleResourceFixTest extends TestCase
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
        $this->createRoleIfNotExists('company');
        $this->createRoleIfNotExists('investor');
        
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
    public function fixed_vehicle_resource_query_filters_correctly()
    {
        // Create vehicles in different states
        $forSaleVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda For Sale',
            'cost' => 500000,
            'plan_sale' => 600000,
            'sale_date' => null,
            'cancelled_at' => null,
        ]);

        $soldVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Tesla Already Sold',
            'cost' => 800000,
            'plan_sale' => 900000,
            'sale_date' => now(),
            'cancelled_at' => null,
            'price' => 950000,
        ]);

        $cancelledVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'BMW Cancelled',
            'cost' => 700000,
            'plan_sale' => 800000,
            'sale_date' => null,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Test cancellation',
        ]);

        echo "\n=== FIXED VEHICLE RESOURCE QUERY TEST ===\n";
        echo "Created vehicles:\n";
        echo "  For Sale: ID:{$forSaleVehicle->id} - {$forSaleVehicle->title} (sale_date: null, cancelled_at: null)\n";
        echo "  Sold: ID:{$soldVehicle->id} - {$soldVehicle->title} (sale_date: {$soldVehicle->sale_date}, cancelled_at: null)\n";
        echo "  Cancelled: ID:{$cancelledVehicle->id} - {$cancelledVehicle->title} (sale_date: null, cancelled_at: {$cancelledVehicle->cancelled_at})\n";

        // Test the FIXED query from VehicleResource
        $fixedResourceVehicles = \App\Filament\Investor\Resources\VehicleResource::getEloquentQuery()->get();
        
        echo "\nFixed VehicleResource query results:\n";
        foreach ($fixedResourceVehicles as $vehicle) {
            echo "  ID:{$vehicle->id} - {$vehicle->title}\n";
        }
        
        // The fixed query should return only truly for-sale vehicles (never sold OR properly unsold)
        // Since the cancelled vehicle in this test still has cancelled_at set, it won't appear
        // Only properly unsold vehicles (with all fields reset to null) should appear
        $this->assertCount(1, $fixedResourceVehicles, 'Only never-sold vehicles should appear');
        $this->assertEquals($forSaleVehicle->id, $fixedResourceVehicles->first()->id);
        $this->assertEquals('Mazda For Sale', $fixedResourceVehicles->first()->title);
        
        echo "\n✅ Fixed query correctly shows only for-sale vehicles (never-sold and properly unsold)\n";
    }

    /** @test */
    public function sell_button_now_targets_correct_vehicle()
    {
        // Create the scenario that caused the original bug
        $mazda = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda MX-30 Red',
            'cost' => 500000,
            'plan_sale' => 600000,
            'sale_date' => null,
            'cancelled_at' => null,
            'created_at' => '2025-01-01 10:00:00',
        ]);

        // Create a cancelled vehicle that previously would appear in the list
        $cancelledTesla = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Tesla Model 3 Blue (Cancelled)',
            'cost' => 800000,
            'plan_sale' => 900000,
            'sale_date' => null,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Changed mind',
            'created_at' => '2025-01-02 10:00:00', // Newer, so would appear first in desc order
        ]);

        echo "\n=== SELL BUTTON TARGETING FIX TEST ===\n";
        echo "Created vehicles:\n";
        echo "  Mazda: ID:{$mazda->id} - For Sale\n";
        echo "  Tesla: ID:{$cancelledTesla->id} - Cancelled (should not appear)\n";

        // Get vehicles as they would appear in the UI
        $visibleVehicles = \App\Filament\Investor\Resources\VehicleResource::getEloquentQuery()
            ->orderBy('id', 'desc')
            ->get();

        echo "\nVehicles visible in UI:\n";
        foreach ($visibleVehicles as $index => $vehicle) {
            echo "  Position {$index}: ID:{$vehicle->id} - {$vehicle->title}\n";
        }

        // Now only the Mazda should be visible (cancelled vehicle won't appear since cancelled_at is set)
        $this->assertCount(1, $visibleVehicles, 'Only never-sold vehicle should be visible');
        $this->assertEquals($mazda->id, $visibleVehicles->first()->id, 'Mazda should be the only visible vehicle');

        // When user clicks "sell" on the first vehicle (Mazda), the correct vehicle gets the action
        $targetVehicle = $visibleVehicles->first();
        $this->assertEquals('Mazda MX-30 Red', $targetVehicle->title);

        // Simulate selling the vehicle
        $salePrice = 650000;
        $this->vehicleService->sellVehicle($targetVehicle, $salePrice);

        // Verify the correct vehicle was sold
        $mazda->refresh();
        $cancelledTesla->refresh();

        $this->assertNotNull($mazda->sale_date, 'Mazda should be sold');
        $this->assertEquals($salePrice, $mazda->price, 'Mazda should have correct price');
        $this->assertNull($cancelledTesla->sale_date, 'Tesla should remain unsold (cancelled)');

        echo "\n✅ Sell button now correctly targets the intended vehicle\n";
        echo "✅ Cancelled vehicles no longer interfere with the UI\n";
    }
}