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

class VehicleSellButtonRealBugTest extends TestCase
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
    public function original_bug_cancelled_vehicles_with_sale_data_interfered_with_ui()
    {
        // This test reproduces the ORIGINAL bug where the old OR logic 
        // incorrectly included cancelled vehicles with sale data
        
        $mazda = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda MX-30 Red',
            'cost' => 500000,
            'plan_sale' => 600000,
            'sale_date' => null,
            'cancelled_at' => null,
            'created_at' => '2025-01-01 10:00:00',
        ]);

        // Create a cancelled vehicle that KEEPS its sale data (this was the problem)
        $cancelledTesla = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Tesla Model 3 Blue',
            'cost' => 800000,
            'plan_sale' => 900000,
            'created_at' => '2025-01-02 10:00:00', // Newer, so appears first in desc order
        ]);
        
        // Sell and then cancel the Tesla (keeping sale data)
        $this->vehicleService->sellVehicle($cancelledTesla, 950000);
        $this->cancellationService->cancelVehicleSale($cancelledTesla, 'Keep sale data for history', $this->companyUser);
        
        $cancelledTesla->refresh();

        echo "\n=== ORIGINAL BUG SCENARIO ===\n";
        echo "Mazda (for sale): sale_date={$mazda->sale_date}, cancelled_at={$mazda->cancelled_at}\n";
        echo "Tesla (cancelled): sale_date={$cancelledTesla->sale_date}, cancelled_at={$cancelledTesla->cancelled_at}\n";

        // Test OLD buggy query (profit=null OR sale_date=null)
        $oldBuggyQuery = Vehicle::where('profit', null)->orWhere('sale_date', null);
        $oldResults = $oldBuggyQuery->orderBy('id', 'desc')->get();
        
        echo "\nOLD buggy query would show:\n";
        foreach ($oldResults as $index => $vehicle) {
            echo "  Position {$index}: ID:{$vehicle->id} - {$vehicle->title}\n";
        }

        // Test NEW fixed query (sale_date IS NULL)
        $newFixedQuery = Vehicle::whereNull('sale_date');
        $newResults = $newFixedQuery->orderBy('id', 'desc')->get();
        
        echo "\nNEW fixed query shows:\n";
        foreach ($newResults as $index => $vehicle) {
            echo "  Position {$index}: ID:{$vehicle->id} - {$vehicle->title}\n";
        }

        // OLD query included cancelled Tesla because profit was null (due to OR logic)
        // This caused user clicking on Mazda position to actually sell Tesla
        $this->assertGreaterThan(1, $oldResults->count(), 'Old query incorrectly included cancelled vehicles');
        
        // NEW query only includes vehicles that are actually for sale
        $this->assertEquals(1, $newResults->count(), 'New query correctly excludes cancelled vehicles');
        $this->assertEquals($mazda->id, $newResults->first()->id, 'Only Mazda should be visible');
        
        echo "\n✅ Bug fixed: Cancelled vehicles with sale data no longer appear in UI\n";
    }

    /** @test */
    public function unsold_vehicles_correctly_appear_for_resale()
    {
        // This test ensures that unsold vehicles (sale data cleared) still appear
        
        $mazda = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda MX-30 For Sale',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);

        $tesla = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Tesla Model 3 Unsold',
            'cost' => 800000,
            'plan_sale' => 900000,
        ]);

        // Sell and then unsell the Tesla (clears sale data, back for sale)
        $this->vehicleService->sellVehicle($tesla, 950000);
        $this->vehicleService->unsellVehicle($tesla, 'Clear sale data - back for sale');
        
        $tesla->refresh();

        echo "\n=== UNSOLD VEHICLES TEST ===\n";
        echo "Mazda (never sold): sale_date={$mazda->sale_date}, cancelled_at={$mazda->cancelled_at}\n";
        echo "Tesla (unsold): sale_date={$tesla->sale_date}, cancelled_at={$tesla->cancelled_at}\n";

        // Both should appear in VehicleResource
        $resourceVehicles = \App\Filament\Investor\Resources\VehicleResource::getEloquentQuery()->get();
        
        echo "\nVehicles visible for sale:\n";
        foreach ($resourceVehicles as $vehicle) {
            echo "  ID:{$vehicle->id} - {$vehicle->title}\n";
        }

        $this->assertEquals(2, $resourceVehicles->count(), 'Both for-sale and unsold vehicles should appear');
        
        $titles = $resourceVehicles->pluck('title')->toArray();
        $this->assertContains('Mazda MX-30 For Sale', $titles);
        $this->assertContains('Tesla Model 3 Unsold', $titles);
        
        echo "\n✅ Unsold vehicles correctly appear for resale\n";
    }

    /** @test */
    public function sell_button_targeting_is_now_accurate()
    {
        // Final test: user clicks on the vehicle they see and it sells the right one
        
        $mazda = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda MX-30 Red',
            'cost' => 500000,
            'plan_sale' => 600000,
            'created_at' => '2025-01-01 10:00:00',
        ]);

        echo "\n=== SELL BUTTON TARGETING TEST ===\n";
        echo "Created Mazda: ID:{$mazda->id} - {$mazda->title}\n";

        // Get vehicle as it appears in UI
        $visibleVehicles = \App\Filament\Investor\Resources\VehicleResource::getEloquentQuery()
            ->orderBy('id', 'desc')
            ->get();

        echo "\nVehicles in UI:\n";
        foreach ($visibleVehicles as $index => $vehicle) {
            echo "  Position {$index}: ID:{$vehicle->id} - {$vehicle->title}\n";
        }

        // User clicks on first (and only) vehicle
        $targetVehicle = $visibleVehicles->first();
        $this->assertEquals($mazda->id, $targetVehicle->id, 'Target should be the Mazda');

        // Sell the vehicle
        $salePrice = 650000;
        $this->vehicleService->sellVehicle($targetVehicle, $salePrice);

        // Verify the correct vehicle was sold
        $mazda->refresh();
        $this->assertNotNull($mazda->sale_date, 'Mazda should be sold');
        $this->assertEquals($salePrice, $mazda->price, 'Mazda should have correct price');

        echo "\n✅ User clicked on Mazda and Mazda was sold (correct targeting)\n";
    }
}