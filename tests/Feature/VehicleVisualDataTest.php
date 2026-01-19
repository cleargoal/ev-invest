<?php

namespace Tests\Feature;

use App\Enums\OperationType;
use App\Filament\Investor\Resources\VehicleResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Payment;
use App\Services\VehicleService;
use App\Services\VehicleCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleVisualDataTest extends TestCase
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
    public function vehicle_resource_query_shows_correct_vehicles()
    {
        // Create vehicles in different states
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

        $cancelledVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Cancelled Vehicle',
            'cost' => 300000,
            'plan_sale' => 400000,
        ]);

        $unsoldVehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Unsold Vehicle',
            'cost' => 200000,
            'plan_sale' => 300000,
        ]);

        // Set up states
        $this->vehicleService->sellVehicle($soldVehicle, 550000);
        
        $this->vehicleService->sellVehicle($cancelledVehicle, 450000);
        $this->cancellationService->cancelVehicleSale($cancelledVehicle, 'Keep sale data', $this->companyUser);

        $this->vehicleService->sellVehicle($unsoldVehicle, 350000);
        $this->vehicleService->unsellVehicle($unsoldVehicle, 'Clear sale data');

        // Test VehicleResource query (what users see in /investor/vehicles)
        $resourceQuery = VehicleResource::getEloquentQuery();
        $visibleVehicles = $resourceQuery->get();

        // Should show: for-sale + unsold vehicles, NOT sold or cancelled
        $this->assertCount(2, $visibleVehicles);
        
        $titles = $visibleVehicles->pluck('title')->toArray();
        $this->assertContains('For Sale Vehicle', $titles);
        $this->assertContains('Unsold Vehicle', $titles);
        $this->assertNotContains('Sold Vehicle', $titles);
        $this->assertNotContains('Cancelled Vehicle', $titles);

        echo "\n=== VEHICLE RESOURCE QUERY TEST ===\n";
        echo "Vehicles visible in VehicleResource: " . $visibleVehicles->count() . "\n";
        echo "Visible vehicles: " . implode(', ', $titles) . "\n";
        
        // Refresh and check states
        $forSaleVehicle->refresh();
        $soldVehicle->refresh();
        $cancelledVehicle->refresh();
        $unsoldVehicle->refresh();
        
        echo "For Sale Vehicle - sale_date:" . ($forSaleVehicle->sale_date ?: 'null') . " profit:" . $forSaleVehicle->getOriginal('profit') . " cancelled_at:" . ($forSaleVehicle->cancelled_at ?: 'null') . "\n";
        echo "Sold Vehicle - sale_date:" . ($soldVehicle->sale_date ? $soldVehicle->sale_date->format('Y-m-d') : 'null') . " profit:" . $soldVehicle->getOriginal('profit') . " cancelled_at:" . ($soldVehicle->cancelled_at ?: 'null') . "\n";
        echo "Cancelled Vehicle - sale_date:" . ($cancelledVehicle->sale_date ? $cancelledVehicle->sale_date->format('Y-m-d') : 'null') . " profit:" . $cancelledVehicle->getOriginal('profit') . " cancelled_at:" . ($cancelledVehicle->cancelled_at ? $cancelledVehicle->cancelled_at->format('Y-m-d') : 'null') . "\n";
        echo "Unsold Vehicle - sale_date:" . ($unsoldVehicle->sale_date ?: 'null') . " profit:" . $unsoldVehicle->getOriginal('profit') . " cancelled_at:" . ($unsoldVehicle->cancelled_at ? $unsoldVehicle->cancelled_at->format('Y-m-d') : 'null') . "\n";
    }

    /** @test */
    public function sold_vehicles_widget_data_is_correct()
    {
        // Create and sell vehicles
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

        $vehicleToUnsell = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Vehicle to Unsell',
            'cost' => 300000,
            'plan_sale' => 400000,
        ]);

        // Sell all vehicles
        $this->vehicleService->sellVehicle($soldVehicle1, 650000);
        $this->vehicleService->sellVehicle($soldVehicle2, 550000);
        $this->vehicleService->sellVehicle($vehicleToUnsell, 450000);

        // Test sold vehicles query before unselling
        $soldVehiclesBefore = Vehicle::sold()->get();
        $this->assertCount(3, $soldVehiclesBefore);

        // Unsell one vehicle
        $this->vehicleService->unsellVehicle($vehicleToUnsell, 'Test unselling');

        // Test sold vehicles query after unselling
        $soldVehiclesAfter = Vehicle::sold()->get();
        $this->assertCount(2, $soldVehiclesAfter);
        
        $titlesAfter = $soldVehiclesAfter->pluck('title')->toArray();
        $this->assertContains('Active Sold Vehicle 1', $titlesAfter);
        $this->assertContains('Active Sold Vehicle 2', $titlesAfter);
        $this->assertNotContains('Vehicle to Unsell', $titlesAfter);

        echo "\n=== SOLD VEHICLES WIDGET DATA TEST ===\n";
        echo "Sold vehicles before unselling: " . $soldVehiclesBefore->count() . "\n";
        echo "Sold vehicles after unselling: " . $soldVehiclesAfter->count() . "\n";
        echo "Remaining sold vehicles: " . implode(', ', $titlesAfter) . "\n";
    }

    /** @test */
    public function cancelled_vehicles_widget_data_is_correct()
    {
        // Create vehicles
        $vehicleToCancel = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Vehicle to Cancel',
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
        $this->vehicleService->sellVehicle($vehicleToCancel, 650000);
        $this->vehicleService->sellVehicle($vehicleToUnsell, 550000);

        // Cancel one (keep sale data) and unsell another (clear sale data)
        $this->cancellationService->cancelVehicleSale($vehicleToCancel, 'Test cancellation', $this->companyUser);
        $this->vehicleService->unsellVehicle($vehicleToUnsell, 'Test unselling');

        // Test cancelled vehicles query (should only show vehicles with sale data)
        $cancelledVehicles = Vehicle::cancelled()->get();
        $this->assertCount(1, $cancelledVehicles);
        $this->assertEquals('Vehicle to Cancel', $cancelledVehicles->first()->title);
        
        // Verify the cancelled vehicle still has sale data
        $cancelledVehicle = $cancelledVehicles->first();
        $this->assertNotNull($cancelledVehicle->sale_date);
        $this->assertGreaterThan(0, $cancelledVehicle->price);
        $this->assertNotNull($cancelledVehicle->cancelled_at);

        echo "\n=== CANCELLED VEHICLES WIDGET DATA TEST ===\n";
        echo "Cancelled vehicles (with sale data): " . $cancelledVehicles->count() . "\n";
        echo "Cancelled vehicle: " . $cancelledVehicle->title . "\n";
        echo "Has sale_date: " . ($cancelledVehicle->sale_date ? 'YES' : 'NO') . "\n";
        echo "Has price: " . ($cancelledVehicle->price > 0 ? 'YES' : 'NO') . "\n";
        echo "Cancelled at: " . $cancelledVehicle->cancelled_at->format('Y-m-d H:i') . "\n";
    }

    /** @test */
    public function payments_are_properly_linked_and_cancelled()
    {
        // Create and sell a vehicle
        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Vehicle for Payment Test',
            'cost' => 500000,
            'plan_sale' => 600000,
        ]);

        $this->vehicleService->sellVehicle($vehicle, 700000);

        // Check payments were created and linked to vehicle
        $vehiclePayments = Payment::where('vehicle_id', $vehicle->id)->get();
        $this->assertGreaterThan(0, $vehiclePayments->count());

        // Should have company revenue and investor income payments
        $companyPayment = $vehiclePayments->where('operation_id', OperationType::REVENUE->value)->first();
        $investorPayment = $vehiclePayments->where('operation_id', OperationType::INCOME->value)->first();
        
        $this->assertNotNull($companyPayment);
        $this->assertNotNull($investorPayment);
        $this->assertEquals($this->companyUser->id, $companyPayment->user_id);
        $this->assertEquals($this->investorUser->id, $investorPayment->user_id);

        // Record initial payment count
        $totalPaymentsBefore = Payment::count();
        $activePaymentsBefore = Payment::active()->count();

        // Unsell the vehicle
        $this->vehicleService->unsellVehicle($vehicle, 'Test payment cancellation');

        // Check that original payments are cancelled
        $vehiclePayments->each(function ($payment) {
            $payment->refresh();
            $this->assertTrue($payment->is_cancelled, "Payment {$payment->id} should be cancelled");
        });

        // Check that compensating payments were created
        $totalPaymentsAfter = Payment::count();
        $activePaymentsAfter = Payment::active()->count();
        
        $this->assertGreaterThan($totalPaymentsBefore, $totalPaymentsAfter);
        
        // Find compensating payments - since cancelled_at is now null, look for WITHDRAW payments for this vehicle
        $compensatingPayments = Payment::where('vehicle_id', $vehicle->id)
            ->where('operation_id', \App\Enums\OperationType::WITHDRAW->value)
            ->where('is_cancelled', false)
            ->get();
        $this->assertGreaterThan(0, $compensatingPayments->count());

        echo "\n=== PAYMENT LINKING AND CANCELLATION TEST ===\n";
        echo "Vehicle payments created: " . $vehiclePayments->count() . "\n";
        echo "Company payment amount: $" . number_format($companyPayment->amount / 100, 2) . "\n";
        echo "Investor payment amount: $" . number_format($investorPayment->amount / 100, 2) . "\n";
        echo "Payments before unselling: {$totalPaymentsBefore}\n";
        echo "Payments after unselling: {$totalPaymentsAfter}\n";
        echo "Compensating payments: " . $compensatingPayments->count() . "\n";
        echo "All original payments cancelled: " . ($vehiclePayments->every(fn($p) => $p->refresh()->is_cancelled) ? 'YES' : 'NO') . "\n";
    }

    /** @test */
    public function vehicle_state_methods_work_correctly()
    {
        // Create vehicles for each state
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

        // Set up states
        $this->vehicleService->sellVehicle($soldVehicle, 500000);
        
        $this->vehicleService->sellVehicle($cancelledVehicle, 400000);
        $this->cancellationService->cancelVehicleSale($cancelledVehicle, 'Keep sale data', $this->companyUser);

        $this->vehicleService->sellVehicle($unsoldVehicle, 300000);
        $this->vehicleService->unsellVehicle($unsoldVehicle, 'Clear sale data');

        // Refresh all vehicles
        $forSaleVehicle->refresh();
        $soldVehicle->refresh();
        $cancelledVehicle->refresh();
        $unsoldVehicle->refresh();

        // Test state methods
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

        echo "\n=== VEHICLE STATE METHODS TEST ===\n";
        echo "For Sale Vehicle: isSold=" . ($forSaleVehicle->isSold() ? 'Y' : 'N') . " isCancelled=" . ($forSaleVehicle->isCancelled() ? 'Y' : 'N') . " isUnsold=" . ($forSaleVehicle->isUnsold() ? 'Y' : 'N') . "\n";
        echo "Sold Vehicle: isSold=" . ($soldVehicle->isSold() ? 'Y' : 'N') . " isCancelled=" . ($soldVehicle->isCancelled() ? 'Y' : 'N') . " isUnsold=" . ($soldVehicle->isUnsold() ? 'Y' : 'N') . "\n";
        echo "Cancelled Vehicle: isSold=" . ($cancelledVehicle->isSold() ? 'Y' : 'N') . " isCancelled=" . ($cancelledVehicle->isCancelled() ? 'Y' : 'N') . " isUnsold=" . ($cancelledVehicle->isUnsold() ? 'Y' : 'N') . "\n";
        echo "Unsold Vehicle: isSold=" . ($unsoldVehicle->isSold() ? 'Y' : 'N') . " isCancelled=" . ($unsoldVehicle->isCancelled() ? 'Y' : 'N') . " isUnsold=" . ($unsoldVehicle->isUnsold() ? 'Y' : 'N') . "\n";
    }
}