<?php

namespace Tests\Feature;

use App\Enums\OperationType;
use App\Filament\Investor\Resources\VehicleResource;
use App\Filament\Investor\Widgets\SoldVehicles;
use App\Filament\Investor\Widgets\CancelledVehicles;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Payment;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentVehicleDisplayTest extends TestCase
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
    public function vehicle_resource_shows_only_for_sale_vehicles()
    {
        // Create different types of vehicles
        $forSaleVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'For Sale Vehicle',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);

        $soldVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Sold Vehicle',
            'cost' => 400000,
            'plan_sale' => 500000,
        ]);
        
        // Sell the vehicle
        $this->vehicleService->sellVehicle($soldVehicle, 550000);

        // Get vehicles that should appear in VehicleResource
        $query = VehicleResource::getEloquentQuery();
        $visibleVehicles = $query->get();

        // Should show only for-sale vehicle, not sold vehicle
        $this->assertCount(1, $visibleVehicles);
        $this->assertEquals($forSaleVehicle->id, $visibleVehicles->first()->id);
        $this->assertEquals('For Sale Vehicle', $visibleVehicles->first()->title);

        echo "\n=== VEHICLE RESOURCE TEST ===\n";
        echo "For sale vehicles shown: " . $visibleVehicles->count() . "\n";
        echo "Vehicle titles: " . $visibleVehicles->pluck('title')->implode(', ') . "\n";
    }

    /** @test */
    public function vehicle_resource_shows_unsold_vehicles_after_cancellation()
    {
        // Create and sell a vehicle
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Vehicle to Unsell',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);

        $this->vehicleService->sellVehicle($vehicle, 700000);

        // Verify it's not in VehicleResource when sold
        $queryBeforeUnsell = VehicleResource::getEloquentQuery();
        $vehiclesBeforeUnsell = $queryBeforeUnsell->get();
        $this->assertCount(0, $vehiclesBeforeUnsell);

        // Unsell the vehicle
        $this->vehicleService->unsellVehicle($vehicle, 'Test unselling');

        // Verify it appears in VehicleResource after unselling
        $queryAfterUnsell = VehicleResource::getEloquentQuery();
        $vehiclesAfterUnsell = $queryAfterUnsell->get();
        
        $this->assertCount(1, $vehiclesAfterUnsell);
        $this->assertEquals($vehicle->id, $vehiclesAfterUnsell->first()->id);
        $this->assertEquals('Vehicle to Unsell', $vehiclesAfterUnsell->first()->title);

        // Verify sale data is cleared
        $vehicle->refresh();
        $this->assertNull($vehicle->sale_date);
        $this->assertEquals(0, $vehicle->price); // MoneyCast converts null to 0
        $this->assertEquals(0, $vehicle->profit);

        echo "\n=== UNSELL VEHICLE RESOURCE TEST ===\n";
        echo "Vehicles before unsell: " . $vehiclesBeforeUnsell->count() . "\n";
        echo "Vehicles after unsell: " . $vehiclesAfterUnsell->count() . "\n";
        echo "Unsold vehicle sale_date: " . ($vehicle->sale_date ?: 'null') . "\n";
        echo "Unsold vehicle price: " . $vehicle->price . "\n";
        echo "Unsold vehicle profit: " . $vehicle->profit . "\n";
    }

    /** @test */
    public function sold_vehicles_widget_shows_only_active_sold_vehicles()
    {
        $this->actingAs($this->companyUser);

        // Create vehicles in different states
        $soldVehicle1 = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Active Sold Vehicle 1',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);

        $soldVehicle2 = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Active Sold Vehicle 2',
            'cost' => 400000,
            'plan_sale' => 500000,
        ]);

        $vehicleToCancel = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Vehicle to Cancel',
            'cost' => 300000,
            'plan_sale' => 400000,
        ]);

        // Sell all vehicles
        $this->vehicleService->sellVehicle($soldVehicle1, 650000);
        $this->vehicleService->sellVehicle($soldVehicle2, 550000);
        $this->vehicleService->sellVehicle($vehicleToCancel, 450000);

        // Test SoldVehicles widget before cancellation
        $component = Livewire::test(SoldVehicles::class);
        $tableData = $component->instance()->table(app(\Filament\Tables\Table::class))
            ->query(\App\Models\Vehicle::sold())
            ->get();

        $this->assertCount(3, $tableData);
        $titles = $tableData->pluck('title')->toArray();
        $this->assertContains('Active Sold Vehicle 1', $titles);
        $this->assertContains('Active Sold Vehicle 2', $titles);
        $this->assertContains('Vehicle to Cancel', $titles);

        // Cancel one vehicle
        $this->vehicleService->unsellVehicle($vehicleToCancel, 'Test cancellation');

        // Test SoldVehicles widget after cancellation
        $componentAfter = Livewire::test(SoldVehicles::class);
        $tableDataAfter = $componentAfter->instance()->table(app(\Filament\Tables\Table::class))
            ->query(\App\Models\Vehicle::sold())
            ->get();

        $this->assertCount(2, $tableDataAfter);
        $titlesAfter = $tableDataAfter->pluck('title')->toArray();
        $this->assertContains('Active Sold Vehicle 1', $titlesAfter);
        $this->assertContains('Active Sold Vehicle 2', $titlesAfter);
        $this->assertNotContains('Vehicle to Cancel', $titlesAfter);

        echo "\n=== SOLD VEHICLES WIDGET TEST ===\n";
        echo "Sold vehicles before cancellation: " . $tableData->count() . "\n";
        echo "Sold vehicles after cancellation: " . $tableDataAfter->count() . "\n";
        echo "Removed from sold list: Vehicle to Cancel\n";
    }

    /** @test */
    public function cancelled_vehicles_widget_shows_only_cancelled_with_sale_data()
    {
        $this->actingAs($this->companyUser);

        // Create and sell vehicles
        $vehicleToCancel1 = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Cancelled Vehicle 1',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);

        $vehicleToUnsell = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Vehicle to Unsell',
            'cost' => 400000,
            'plan_sale' => 500000,
        ]);

        // Sell both vehicles
        $this->vehicleService->sellVehicle($vehicleToCancel1, 650000);
        $this->vehicleService->sellVehicle($vehicleToUnsell, 550000);

        // Cancel one vehicle (should appear in CancelledVehicles)
        $result = app(\App\Services\VehicleCancellationService::class)
            ->cancelVehicleSale($vehicleToCancel1, 'Test cancellation - keep sale data', $this->companyUser);
        $this->assertTrue($result);

        // Unsell another vehicle (should NOT appear in CancelledVehicles)
        $this->vehicleService->unsellVehicle($vehicleToUnsell, 'Test unselling - clear sale data');

        // Test CancelledVehicles widget
        $component = Livewire::test(CancelledVehicles::class);
        $tableData = $component->instance()->table(app(\Filament\Tables\Table::class))
            ->query(\App\Models\Vehicle::cancelled())
            ->get();

        // Should only show cancelled vehicle with sale data, not unsold vehicle
        $this->assertCount(1, $tableData);
        $this->assertEquals('Cancelled Vehicle 1', $tableData->first()->title);
        $this->assertNotNull($tableData->first()->sale_date);
        $this->assertGreaterThan(0, $tableData->first()->price);

        echo "\n=== CANCELLED VEHICLES WIDGET TEST ===\n";
        echo "Cancelled vehicles shown: " . $tableData->count() . "\n";
        echo "Cancelled vehicle title: " . $tableData->first()->title . "\n";
        echo "Has sale data: " . ($tableData->first()->sale_date ? 'YES' : 'NO') . "\n";
    }

    /** @test */
    public function vehicle_states_are_correctly_identified()
    {
        // Create vehicles in all possible states
        $forSaleVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'For Sale Vehicle',
            'cost' => 500000,
        ]);

        $soldVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Sold Vehicle',
            'cost' => 400000,
        ]);

        $cancelledVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Cancelled Vehicle',
            'cost' => 300000,
        ]);

        $unsoldVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Unsold Vehicle',
            'cost' => 200000,
        ]);

        // Set up different states
        $this->vehicleService->sellVehicle($soldVehicle, 500000);
        
        $this->vehicleService->sellVehicle($cancelledVehicle, 400000);
        app(\App\Services\VehicleCancellationService::class)
            ->cancelVehicleSale($cancelledVehicle, 'Keep sale data', $this->companyUser);

        $this->vehicleService->sellVehicle($unsoldVehicle, 300000);
        $this->vehicleService->unsellVehicle($unsoldVehicle, 'Clear sale data');

        // Refresh all vehicles
        $forSaleVehicle->refresh();
        $soldVehicle->refresh();
        $cancelledVehicle->refresh();
        $unsoldVehicle->refresh();

        // Test vehicle state methods
        $this->assertFalse($forSaleVehicle->isSold());
        $this->assertFalse($forSaleVehicle->isCancelled());
        $this->assertFalse($forSaleVehicle->isUnsold());

        $this->assertTrue($soldVehicle->isSold());
        $this->assertFalse($soldVehicle->isCancelled());
        $this->assertFalse($soldVehicle->isUnsold());

        $this->assertFalse($cancelledVehicle->isSold());
        $this->assertTrue($cancelledVehicle->isCancelled());
        $this->assertFalse($cancelledVehicle->isUnsold());

        $this->assertFalse($unsoldVehicle->isSold());
        $this->assertFalse($unsoldVehicle->isCancelled());
        $this->assertTrue($unsoldVehicle->isUnsold());

        // Test scope queries
        $forSaleCount = Vehicle::where(function($q) {
            $q->whereNull('profit')->orWhere('profit', 0);
        })->whereNull('sale_date')->count();

        $soldCount = Vehicle::sold()->count();
        $cancelledCount = Vehicle::cancelled()->count();

        $this->assertEquals(2, $forSaleCount); // for-sale + unsold
        $this->assertEquals(1, $soldCount);
        $this->assertEquals(1, $cancelledCount);

        echo "\n=== VEHICLE STATES TEST ===\n";
        echo "For sale vehicles (including unsold): {$forSaleCount}\n";
        echo "Sold vehicles: {$soldCount}\n";
        echo "Cancelled vehicles (with sale data): {$cancelledCount}\n";
        echo "For Sale Vehicle - isSold:" . ($forSaleVehicle->isSold() ? 'Y' : 'N') . " isCancelled:" . ($forSaleVehicle->isCancelled() ? 'Y' : 'N') . " isUnsold:" . ($forSaleVehicle->isUnsold() ? 'Y' : 'N') . "\n";
        echo "Sold Vehicle - isSold:" . ($soldVehicle->isSold() ? 'Y' : 'N') . " isCancelled:" . ($soldVehicle->isCancelled() ? 'Y' : 'N') . " isUnsold:" . ($soldVehicle->isUnsold() ? 'Y' : 'N') . "\n";
        echo "Cancelled Vehicle - isSold:" . ($cancelledVehicle->isSold() ? 'Y' : 'N') . " isCancelled:" . ($cancelledVehicle->isCancelled() ? 'Y' : 'N') . " isUnsold:" . ($cancelledVehicle->isUnsold() ? 'Y' : 'N') . "\n";
        echo "Unsold Vehicle - isSold:" . ($unsoldVehicle->isSold() ? 'Y' : 'N') . " isCancelled:" . ($unsoldVehicle->isCancelled() ? 'Y' : 'N') . " isUnsold:" . ($unsoldVehicle->isUnsold() ? 'Y' : 'N') . "\n";
    }

    /** @test */
    public function company_role_can_see_unsell_button_in_sold_vehicles_widget()
    {
        $this->actingAs($this->companyUser);

        // Create and sell a vehicle
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Vehicle with Unsell Button',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);

        $this->vehicleService->sellVehicle($vehicle, 650000);

        // Test SoldVehicles widget with company user
        $component = Livewire::test(SoldVehicles::class);
        
        // The unsell action should be available for company role
        $actions = $component->instance()->table(app(\Filament\Tables\Table::class))->getActions();
        $unsellAction = collect($actions)->first(fn($action) => $action->getName() === 'unsell');
        
        $this->assertNotNull($unsellAction, 'Unsell action should be available');
        
        // Test that the action is visible for company role
        $isVisible = $unsellAction->isVisible();
        $this->assertTrue($isVisible, 'Unsell action should be visible for company role');

        echo "\n=== UNSELL BUTTON VISIBILITY TEST ===\n";
        echo "Company user can see unsell button: " . ($isVisible ? 'YES' : 'NO') . "\n";
    }

    /** @test */
    public function investor_role_cannot_see_unsell_button_in_sold_vehicles_widget()
    {
        $this->actingAs($this->investorUser);

        // Create and sell a vehicle
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Vehicle - No Unsell for Investor',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);

        $this->vehicleService->sellVehicle($vehicle, 650000);

        // Test SoldVehicles widget with investor user
        $component = Livewire::test(SoldVehicles::class);
        
        // The unsell action should not be visible for investor role
        $actions = $component->instance()->table(app(\Filament\Tables\Table::class))->getActions();
        $unsellAction = collect($actions)->first(fn($action) => $action->getName() === 'unsell');
        
        if ($unsellAction) {
            $isVisible = $unsellAction->isVisible();
            $this->assertFalse($isVisible, 'Unsell action should not be visible for investor role');
            
            echo "\n=== INVESTOR UNSELL BUTTON TEST ===\n";
            echo "Investor user can see unsell button: " . ($isVisible ? 'YES' : 'NO') . "\n";
        } else {
            echo "\n=== INVESTOR UNSELL BUTTON TEST ===\n";
            echo "Unsell action not found for investor (expected)\n";
        }
    }
}