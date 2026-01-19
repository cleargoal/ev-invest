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

class SoldVehiclesWidgetFixTest extends TestCase
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
    public function sold_vehicles_widget_shows_all_scenarios_correctly()
    {
        // Create different vehicle scenarios
        
        // 1. Normal sold vehicle (never cancelled)
        $normalSold = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Normal Sold Vehicle',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);
        
        // 2. Cancelled then sold again vehicle
        $cancelledThenSold = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Cancelled Then Sold Vehicle',
            'cost' => 600000,
            'plan_sale' => 700000,
        ]);
        
        // 3. Unsold then sold again vehicle
        $unsoldThenSold = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Unsold Then Sold Vehicle',
            'cost' => 700000,
            'plan_sale' => 800000,
        ]);
        
        // 4. Currently cancelled vehicle (should NOT appear in sold widget)
        $currentlyCancelled = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Currently Cancelled Vehicle',
            'cost' => 800000,
            'plan_sale' => 900000,
        ]);
        
        // 5. For sale vehicle (should NOT appear in sold widget)
        $forSale = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'For Sale Vehicle',
            'cost' => 900000,
            'plan_sale' => 1000000,
        ]);

        echo "\n=== SOLD VEHICLES WIDGET COMPREHENSIVE TEST ===\n";

        // Process scenario 1: Normal sale
        $this->vehicleService->sellVehicle($normalSold, 650000);
        echo "1. Normal sold vehicle: ✓\n";

        // Process scenario 2: Sell -> Cancel -> Sell again
        $this->vehicleService->sellVehicle($cancelledThenSold, 750000);
        $this->cancellationService->cancelVehicleSale($cancelledThenSold, 'Customer issue', $this->companyUser);
        sleep(1); // Ensure different timestamp
        $this->vehicleService->sellVehicle($cancelledThenSold, 780000);
        echo "2. Cancelled then sold vehicle: ✓\n";

        // Process scenario 3: Sell -> Unsell -> Sell again
        $this->vehicleService->sellVehicle($unsoldThenSold, 850000);
        $this->vehicleService->unsellVehicle($unsoldThenSold, 'Clear sale data');
        sleep(1); // Ensure different timestamp
        $this->vehicleService->sellVehicle($unsoldThenSold, 880000);
        echo "3. Unsold then sold vehicle: ✓\n";

        // Process scenario 4: Sell -> Cancel (keep sale data)
        $this->vehicleService->sellVehicle($currentlyCancelled, 950000);
        $this->cancellationService->cancelVehicleSale($currentlyCancelled, 'Final cancellation', $this->companyUser);
        echo "4. Currently cancelled vehicle: ✓\n";

        // Scenario 5: Leave for sale (no action needed)
        echo "5. For sale vehicle: ✓\n";

        // Check the sold vehicles widget query
        $soldVehicles = Vehicle::sold()->get();
        
        echo "\nVehicles appearing in SoldVehicles widget:\n";
        foreach ($soldVehicles as $vehicle) {
            echo "  - {$vehicle->title} (ID: {$vehicle->id})\n";
        }

        // Assertions
        $soldTitles = $soldVehicles->pluck('title')->toArray();
        
        // Should appear in sold widget
        $this->assertContains('Normal Sold Vehicle', $soldTitles, 'Normal sold vehicle should appear');
        $this->assertContains('Cancelled Then Sold Vehicle', $soldTitles, 'Cancelled then sold vehicle should appear');
        $this->assertContains('Unsold Then Sold Vehicle', $soldTitles, 'Unsold then sold vehicle should appear');
        
        // Should NOT appear in sold widget
        $this->assertNotContains('Currently Cancelled Vehicle', $soldTitles, 'Currently cancelled vehicle should NOT appear');
        $this->assertNotContains('For Sale Vehicle', $soldTitles, 'For sale vehicle should NOT appear');

        // Verify correct count
        $this->assertEquals(3, $soldVehicles->count(), 'Exactly 3 vehicles should appear in sold widget');

        echo "\n✅ SoldVehicles widget correctly shows:\n";
        echo "   - Normal sold vehicles\n";
        echo "   - Previously cancelled but now sold vehicles\n";
        echo "   - Previously unsold but now sold vehicles\n";
        echo "   - Excludes currently cancelled vehicles\n";
        echo "   - Excludes for-sale vehicles\n";
    }
}