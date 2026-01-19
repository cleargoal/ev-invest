<?php

namespace Tests\Unit;

use App\Enums\OperationType;
use App\Events\TotalChangedEvent;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VehicleServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleService $vehicleService;
    private User $companyUser;
    private User $investorUser;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        $this->createRoleIfNotExists('company');
        $this->createRoleIfNotExists('investor');
        $this->createRoleIfNotExists('operator');

        // Create users
        $this->companyUser = User::factory()->create();
        $this->companyUser->assignRole('company');
        
        $this->investorUser = User::factory()->create();
        $this->investorUser->assignRole('investor');

        // Create a test vehicle
        $this->vehicle = Vehicle::factory()->create([
            'user_id' => $this->companyUser->id,
            'title' => 'Test Vehicle',
            'cost' => 1000.00, // $1000
            'produced' => 2020,
            'mileage' => 50000,
            'created_at' => now()->subDays(30)
        ]);

        $this->vehicleService = app(VehicleService::class);
    }

    public function test_sell_vehicle_transaction_success()
    {
        Event::fake([TotalChangedEvent::class]);

        // Create investor payment first, then contribution for income calculation
        $payment = Payment::create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 500.00,
            'confirmed' => true,
        ]);
        
        $this->investorUser->contributions()->create([
            'payment_id' => $payment->id,
            'percents' => 500000, // 50% share
            'amount' => 500.00,
        ]);

        $salePrice = 1500.00; // $1500 - $500 profit
        $saleDate = Carbon::now();

        $result = $this->vehicleService->sellVehicle($this->vehicle, $salePrice, $saleDate);

        // Verify vehicle was updated
        $this->vehicle->refresh();
        $this->assertEquals($salePrice, $this->vehicle->price);
        $this->assertEquals(500.00, $this->vehicle->profit); // $1500 - $1000
        $this->assertNotNull($this->vehicle->sale_date);

        // Verify company commission payment was created
        $companyPayment = Payment::where('user_id', $this->companyUser->id)
            ->where('operation_id', OperationType::REVENUE)
            ->first();
        
        $this->assertNotNull($companyPayment);
        $this->assertEquals(250.00, $companyPayment->amount); // 50% of profit
        $this->assertTrue($companyPayment->confirmed);

        // Verify investor income payment was created
        $investorPayment = Payment::where('user_id', $this->investorUser->id)
            ->where('operation_id', OperationType::INCOME)
            ->first();
        
        $this->assertNotNull($investorPayment);
        $this->assertEquals(125.00, $investorPayment->amount); // 50% of (50% profit share)
        $this->assertTrue($investorPayment->confirmed);

        // Verify total was created
        $total = Total::where('payment_id', $companyPayment->id)->first();
        $this->assertNotNull($total);

        // Verify event was dispatched
        Event::assertDispatched(TotalChangedEvent::class);
    }

    public function test_sell_vehicle_transaction_rollback_on_failure()
    {
        Event::fake([TotalChangedEvent::class]);

        // Create investor payment first, then contribution
        $payment = Payment::create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 500.00,
            'confirmed' => true,
        ]);
        
        $this->investorUser->contributions()->create([
            'payment_id' => $payment->id,
            'percents' => 500000,
            'amount' => 500.00,
        ]);

        // Mock a database failure by making the company user null
        // This will cause the companyCommissions method to fail
        $this->companyUser->delete();

        $salePrice = 1500.00;
        $saleDate = Carbon::now();

        // Expect an exception to be thrown
        $this->expectException(\Throwable::class);

        try {
            $this->vehicleService->sellVehicle($this->vehicle, $salePrice, $saleDate);
        } catch (\Throwable $e) {
            // Verify that the vehicle was NOT updated due to transaction rollback
            $this->vehicle->refresh();
            $this->assertNull($this->vehicle->sale_date);
            $this->assertEquals(0.0, $this->vehicle->price); // Vehicle price defaults to 0.0
            $this->assertEquals(0.0, $this->vehicle->profit); // MoneyCast returns 0.0 for null

            // Verify only the setup payment exists (transaction was rolled back)
            $this->assertEquals(1, Payment::count());

            // Verify no totals were created
            $this->assertEquals(0, Total::count());

            // Verify event was NOT dispatched due to rollback
            Event::assertNotDispatched(TotalChangedEvent::class);

            throw $e;
        }
    }

    public function test_buy_vehicle_creates_vehicle_and_dispatches_event()
    {
        Event::fake(\App\Events\BoughtAutoEvent::class);

        $vehicleData = [
            'title' => 'Test Car',
            'produced' => 2020,
            'mileage' => 50000,
            'cost' => 1200.00,
            'user_id' => $this->companyUser->id,
        ];

        $result = $this->vehicleService->buyVehicle($vehicleData);

        $this->assertInstanceOf(Vehicle::class, $result);
        $this->assertEquals('Test Car', $result->title);
        $this->assertEquals(1200.00, $result->cost);
        $this->assertNotNull($result->created_at);

        Event::assertDispatched(\App\Events\BoughtAutoEvent::class);
    }

    public function test_company_commissions_calculation()
    {
        $this->vehicle->update([
            'price' => 1500.00,
            'profit' => 500.00,
            'sale_date' => now(),
        ]);

        $payment = $this->vehicleService->companyCommissions($this->vehicle);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals($this->companyUser->id, $payment->user_id);
        $this->assertEquals(OperationType::REVENUE, $payment->operation_id);
        $this->assertEquals(250.00, $payment->amount); // 50% of profit
        $this->assertTrue($payment->confirmed);
    }

    public function test_invest_income_distribution()
    {
        // Create multiple investors with different contribution percentages
        $investor1 = User::factory()->create();
        $investor1->assignRole('investor');
        // Create payments first, then contributions
        $payment1 = Payment::create([
            'user_id' => $investor1->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 600.00,
            'confirmed' => true,
        ]);
        
        $investor1->contributions()->create([
            'payment_id' => $payment1->id,
            'percents' => 600000, // 60%
            'amount' => 600.00,
        ]);

        $investor2 = User::factory()->create();
        $investor2->assignRole('investor');
        
        $payment2 = Payment::create([
            'user_id' => $investor2->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 400.00,
            'confirmed' => true,
        ]);
        
        $investor2->contributions()->create([
            'payment_id' => $payment2->id,
            'percents' => 400000, // 40%
            'amount' => 400.00,
        ]);

        $this->vehicle->update([
            'profit' => 1000.00,
            'sale_date' => now(),
        ]);

        $investorCount = $this->vehicleService->investIncome($this->vehicle);

        $this->assertEquals(2, $investorCount);

        // Verify investor payments were created with correct amounts
        $investor1Payment = Payment::where('user_id', $investor1->id)
            ->where('operation_id', OperationType::INCOME)
            ->first();
        
        $investor2Payment = Payment::where('user_id', $investor2->id)
            ->where('operation_id', OperationType::INCOME)
            ->first();

        $this->assertNotNull($investor1Payment);
        $this->assertNotNull($investor2Payment);

        // 50% of profit for sharing = $500
        // Investor1: $500 * 0.6 = $300
        // Investor2: $500 * 0.4 = $200
        $this->assertEquals(300.00, $investor1Payment->amount);
        $this->assertEquals(200.00, $investor2Payment->amount);
    }

    public function test_sell_vehicle_with_no_investors()
    {
        Event::fake([TotalChangedEvent::class]);

        $salePrice = 1500.00;
        $result = $this->vehicleService->sellVehicle($this->vehicle, $salePrice);

        // Should still work, just no investor payments created
        $this->assertEquals($salePrice, $result->price);
        
        // Verify company payment was created
        $companyPayment = Payment::where('user_id', $this->companyUser->id)->first();
        $this->assertNotNull($companyPayment);

        // Verify no investor payments were created
        $investorPayments = Payment::where('operation_id', OperationType::INCOME)->count();
        $this->assertEquals(0, $investorPayments);
    }

    public function test_sell_vehicle_with_zero_profit_does_not_crash()
    {
        Event::fake([TotalChangedEvent::class]);

        // Create investor payment first, then contribution
        $payment = Payment::create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 500.00,
            'confirmed' => true,
        ]);
        
        $this->investorUser->contributions()->create([
            'payment_id' => $payment->id,
            'percents' => 500000,
            'amount' => 500.00,
        ]);

        // Sell vehicle at cost price (zero profit)
        $salePrice = $this->vehicle->cost; // Same as cost = 0 profit
        
        $result = $this->vehicleService->sellVehicle($this->vehicle, $salePrice);

        // Verify vehicle was updated
        $this->vehicle->refresh();
        $this->assertEquals($salePrice, $this->vehicle->price);
        $this->assertEquals(0.0, $this->vehicle->profit); // Zero profit
        $this->assertNotNull($this->vehicle->sale_date);

        // Verify NO company commission payment was created (zero profit)
        $companyPayment = Payment::where('user_id', $this->companyUser->id)
            ->where('operation_id', OperationType::REVENUE)
            ->first();
        
        $this->assertNull($companyPayment);

        // Verify NO investor income payment was created (zero profit)
        $investorPayment = Payment::where('user_id', $this->investorUser->id)
            ->where('operation_id', OperationType::INCOME)
            ->first();
        
        $this->assertNull($investorPayment);

        // Verify NO total was created (no commission payment)
        $total = Total::count();
        $this->assertEquals(0, $total);

        // Verify event was still dispatched (but with zero amount)
        Event::assertDispatched(TotalChangedEvent::class);
    }

    public function test_sell_vehicle_with_negative_profit_does_not_crash()
    {
        Event::fake([TotalChangedEvent::class]);

        // Create investor payment first, then contribution
        $payment = Payment::create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 500.00,
            'confirmed' => true,
        ]);
        
        $this->investorUser->contributions()->create([
            'payment_id' => $payment->id,
            'percents' => 500000,
            'amount' => 500.00,
        ]);

        // Sell vehicle below cost price (negative profit)
        $salePrice = $this->vehicle->cost - 200.00; // Loss of $200
        
        $result = $this->vehicleService->sellVehicle($this->vehicle, $salePrice);

        // Verify vehicle was updated
        $this->vehicle->refresh();
        $this->assertEquals($salePrice, $this->vehicle->price);
        $this->assertEquals(-200.0, $this->vehicle->profit); // Negative profit
        $this->assertNotNull($this->vehicle->sale_date);

        // Verify NO company commission payment was created (negative profit)
        $companyPayment = Payment::where('user_id', $this->companyUser->id)
            ->where('operation_id', OperationType::REVENUE)
            ->first();
        
        $this->assertNull($companyPayment);

        // Verify NO investor income payment was created (negative profit)
        $investorPayment = Payment::where('user_id', $this->investorUser->id)
            ->where('operation_id', OperationType::INCOME)
            ->first();
        
        $this->assertNull($investorPayment);

        // Verify NO total was created (no commission payment)
        $total = Total::count();
        $this->assertEquals(0, $total);

        // Verify event was still dispatched (but with zero amount)
        Event::assertDispatched(TotalChangedEvent::class);
    }
}