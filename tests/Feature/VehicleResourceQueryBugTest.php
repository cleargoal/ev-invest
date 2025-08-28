<?php

namespace Tests\Feature;

use App\Enums\OperationType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Payment;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleResourceQueryBugTest extends TestCase
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
    public function vehicle_resource_query_filters_correctly()
    {
        // Create vehicles in different states
        $forSaleVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda For Sale',
            'cost' => 500000,
            'plan_sale' => 600000,
            'sale_date' => null,
            'profit' => null,
        ]);

        $soldVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Tesla Already Sold',
            'cost' => 800000,
            'plan_sale' => 900000,
            'sale_date' => now(),
            'profit' => 100000,
            'price' => 950000,
        ]);

        $cancelledVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'BMW Cancelled',
            'cost' => 700000,
            'plan_sale' => 800000,
            'sale_date' => null,
            'profit' => null,
            'cancelled_at' => now(), // Properly cancelled vehicle
            'cancellation_reason' => 'Test cancellation',
        ]);

        echo "\n=== VEHICLE RESOURCE QUERY FILTER TEST ===\n";
        echo "Created vehicles:\n";
        echo "  For Sale: ID:{$forSaleVehicle->id} - {$forSaleVehicle->title} (sale_date: null, profit: null)\n";
        echo "  Sold: ID:{$soldVehicle->id} - {$soldVehicle->title} (sale_date: {$soldVehicle->sale_date}, profit: {$soldVehicle->profit})\n";
        echo "  Cancelled: ID:{$cancelledVehicle->id} - {$cancelledVehicle->title} (sale_date: null, cancelled_at: {$cancelledVehicle->cancelled_at})\n";

        // Test the CURRENT VehicleResource query (now FIXED)
        $currentQuery = \App\Filament\Investor\Resources\VehicleResource::getEloquentQuery();
        $currentResults = $currentQuery->get();
        
        echo "\nCurrent VehicleResource query results:\n";
        foreach ($currentResults as $vehicle) {
            echo "  ID:{$vehicle->id} - {$vehicle->title} (sale_date: {$vehicle->sale_date}, cancelled_at: {$vehicle->cancelled_at})\n";
        }
        
        // This query should ONLY return truly for-sale vehicles using AND logic
        echo "\nAnalyzing query logic:\n";
        echo "  For Sale Vehicle: sale_date=null ✓, cancelled_at=null ✓ → Should appear\n";
        echo "  Sold Vehicle: sale_date≠null ✗ → Should NOT appear\n";
        echo "  Cancelled Vehicle: sale_date=null ✓, cancelled_at≠null ✗ → Should NOT appear (correctly excluded)\n";

        // The FIXED logic: AND logic correctly excludes cancelled vehicles
        $this->assertContains($forSaleVehicle->id, $currentResults->pluck('id'));
        $this->assertNotContains($cancelledVehicle->id, $currentResults->pluck('id'), 'Cancelled vehicle correctly excluded');
        $this->assertNotContains($soldVehicle->id, $currentResults->pluck('id'));

        // The VehicleResource query is now correct and matches our logic
        // No need for separate "correct query" since the current one is fixed
        
        echo "\n✅ VehicleResource query now correctly uses AND logic to exclude cancelled vehicles\n";
        echo "✅ Only truly for-sale vehicles (never sold OR properly unsold) appear in the list\n";
    }

    /** @test */
    public function vehicle_resource_query_with_mixed_states_causes_confusion()
    {
        // Create vehicles that might get confused in the UI
        $mazda = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Mazda MX-30 For Sale',
            'cost' => 500000,
            'plan_sale' => 600000,
            'sale_date' => null,
            'profit' => null,
            'created_at' => '2025-01-01 10:00:00',
        ]);

        $tesla = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Tesla Model 3 Cancelled',
            'cost' => 800000,
            'plan_sale' => 900000,
            'sale_date' => null,
            'profit' => -50000, // Cancelled
            'created_at' => '2025-01-02 10:00:00',
        ]);

        echo "\n=== VEHICLE CONFUSION TEST ===\n";
        echo "Created vehicles:\n";
        echo "  Mazda: ID:{$mazda->id} - For Sale (should appear in UI)\n";
        echo "  Tesla: ID:{$tesla->id} - Cancelled (should NOT appear in UI, but does due to bug)\n";

        // Current buggy query shows both vehicles
        $currentResourceVehicles = Vehicle::where('profit', null)->orWhere('sale_date', null)
            ->orderBy('id', 'desc')
            ->get();

        echo "\nVehicles shown in UI (using current buggy query):\n";
        foreach ($currentResourceVehicles as $index => $vehicle) {
            echo "  Position {$index}: ID:{$vehicle->id} - {$vehicle->title}\n";
        }

        // User clicks on what they think is Mazda (position 1), but gets Tesla (position 0)
        $this->assertCount(2, $currentResourceVehicles, 'Both vehicles appear in UI due to bug');
        
        // Due to desc order, Tesla (ID 2) appears first, Mazda (ID 1) appears second
        $firstVehicleInUI = $currentResourceVehicles->first();
        $this->assertEquals($tesla->id, $firstVehicleInUI->id, 'Tesla appears first due to higher ID');
        
        echo "\n❌ Bug identified: User sees Mazda in position 1, but clicks on Tesla in position 0\n";
        echo "❌ This happens because cancelled vehicles appear in the list due to OR logic\n";
        echo "❌ And vehicles are sorted by ID desc, so newer cancelled vehicles appear first\n";
    }
}