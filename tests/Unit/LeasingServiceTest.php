<?php

namespace Tests\Unit;

use App\Enums\OperationType;
use App\Models\Leasing;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use App\Notifications\LeasingIncomeNotification;
use App\Services\LeasingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeasingServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeasingService $leasingService;
    private User $companyUser;
    private User $investorUser;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        $this->createRoleIfNotExists('company');
        $this->createRoleIfNotExists('investor');
        $this->createRoleIfNotExists('admin');

        // Create users
        $this->companyUser = User::factory()->create();
        $this->companyUser->assignRole('company');
        
        $this->investorUser = User::factory()->create();
        $this->investorUser->assignRole('investor');

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->leasingService = app(LeasingService::class);
    }

    public function test_get_leasing_transaction_success()
    {
        Notification::fake();
        config(['app.env' => 'local']); // Set environment for notification target

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

        $leasingData = [
            'vehicle_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'duration' => 365,
            'price' => 200.00, // $200 leasing income
        ];

        $result = $this->leasingService->getLeasing($leasingData);

        // Verify leasing record was created
        $this->assertInstanceOf(Leasing::class, $result);
        $this->assertEquals(1, $result->vehicle_id);
        $this->assertEquals(200.00, $result->price);
        $this->assertNotNull($result->created_at);

        // Verify company commission payment was created
        $companyPayment = Payment::where('user_id', $this->companyUser->id)
            ->where('operation_id', OperationType::C_LEASING)
            ->first();
        
        $this->assertNotNull($companyPayment);
        $this->assertEquals(100.00, $companyPayment->amount); // 50% of leasing price
        $this->assertTrue($companyPayment->confirmed);

        // Verify investor income payment was created
        $investorPayment = Payment::where('user_id', $this->investorUser->id)
            ->where('operation_id', OperationType::I_LEASING)
            ->first();
        
        $this->assertNotNull($investorPayment);
        $this->assertEquals(50.00, $investorPayment->amount); // 50% of (50% share)
        $this->assertTrue($investorPayment->confirmed);

        // Verify total was created
        $total = Total::where('payment_id', $companyPayment->id)->first();
        $this->assertNotNull($total);

        // Verify notification was sent
        Notification::assertSentTo($this->adminUser, LeasingIncomeNotification::class);
    }

    public function test_get_leasing_transaction_rollback_on_failure()
    {
        Notification::fake();

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

        // Mock a database failure by deleting the company user
        $this->companyUser->delete();

        $leasingData = [
            'vehicle_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'duration' => 365,
            'price' => 200.00,
        ];

        // Expect an exception to be thrown
        $this->expectException(\Throwable::class);

        try {
            $this->leasingService->getLeasing($leasingData);
        } catch (\Throwable $e) {
            // Verify that no leasing record was created due to transaction rollback
            $this->assertEquals(0, Leasing::count());

            // Verify only the setup payment exists (transaction was rolled back)
            $this->assertEquals(1, Payment::count());

            // Verify no totals were created
            $this->assertEquals(0, Total::count());

            // Verify no notification was sent due to rollback
            Notification::assertNothingSent();

            throw $e;
        }
    }

    public function test_company_commissions_calculation()
    {
        $leasing = Leasing::create([
            'vehicle_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'duration' => 365,
            'price' => 400.00,
            'created_at' => now(),
        ]);

        $payment = $this->leasingService->companyCommissions($leasing);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals($this->companyUser->id, $payment->user_id);
        $this->assertEquals(OperationType::C_LEASING, $payment->operation_id);
        $this->assertEquals(200.00, $payment->amount); // 50% of leasing price
        $this->assertTrue($payment->confirmed);
        $this->assertEquals($leasing->created_at, $payment->created_at);
    }

    public function test_invest_income_distribution_multiple_investors()
    {
        // Create multiple investors with different contribution percentages
        $investor1 = User::factory()->create();
        $investor1->assignRole('investor');
        
        // Create payments first, then contributions
        $payment1 = Payment::create([
            'user_id' => $investor1->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 700.00,
            'confirmed' => true,
        ]);
        
        $investor1->contributions()->create([
            'payment_id' => $payment1->id,
            'percents' => 700000, // 70%
            'amount' => 700.00,
        ]);

        $investor2 = User::factory()->create();
        $investor2->assignRole('investor');
        
        $payment2 = Payment::create([
            'user_id' => $investor2->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 300.00,
            'confirmed' => true,
        ]);
        
        $investor2->contributions()->create([
            'payment_id' => $payment2->id,
            'percents' => 300000, // 30%
            'amount' => 300.00,
        ]);

        $leasing = Leasing::create([
            'vehicle_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'duration' => 365,
            'price' => 1000.00,
            'created_at' => now(),
        ]);

        $investorCount = $this->leasingService->investIncome($leasing);

        $this->assertEquals(2, $investorCount);

        // Verify investor payments were created with correct amounts
        $investor1Payment = Payment::where('user_id', $investor1->id)
            ->where('operation_id', OperationType::I_LEASING)
            ->first();
        
        $investor2Payment = Payment::where('user_id', $investor2->id)
            ->where('operation_id', OperationType::I_LEASING)
            ->first();

        $this->assertNotNull($investor1Payment);
        $this->assertNotNull($investor2Payment);

        // 50% of leasing price for sharing = $500
        // Investor1: $500 * 0.7 = $350
        // Investor2: $500 * 0.3 = $150
        $this->assertEquals(350.00, $investor1Payment->amount);
        $this->assertEquals(150.00, $investor2Payment->amount);
    }

    public function test_get_leasing_with_custom_created_at()
    {
        Notification::fake();

        // Create investor payment first, then contribution
        $payment = Payment::create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 1000.00,
            'confirmed' => true,
        ]);
        
        $this->investorUser->contributions()->create([
            'payment_id' => $payment->id,
            'percents' => 1000000, // 100% share
            'amount' => 1000.00,
        ]);

        $customDate = Carbon::create(2024, 1, 15, 10, 30, 0);
        $leasingData = [
            'vehicle_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'duration' => 365,
            'price' => 300.00,
            'created_at' => $customDate,
        ];

        $result = $this->leasingService->getLeasing($leasingData);

        $this->assertEquals($customDate, $result->created_at);

        // Verify payments have the same custom date
        $companyPayment = Payment::where('user_id', $this->companyUser->id)
            ->where('operation_id', OperationType::C_LEASING)
            ->first();
        $investorIncomePayment = Payment::where('user_id', $this->investorUser->id)
            ->where('operation_id', OperationType::I_LEASING)
            ->first();

        $this->assertEquals($customDate, $companyPayment->created_at);
        $this->assertEquals($customDate, $investorIncomePayment->created_at);
    }

    public function test_notification_sent_to_correct_users_based_on_environment()
    {
        Notification::fake();

        // Test local environment (should notify admin)
        config(['app.env' => 'local']);

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

        $leasingData = [
            'vehicle_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'duration' => 365,
            'price' => 100.00,
        ];

        $this->leasingService->getLeasing($leasingData);

        // Should notify admin user in local environment
        Notification::assertSentTo($this->adminUser, LeasingIncomeNotification::class);
        Notification::assertNotSentTo($this->investorUser, LeasingIncomeNotification::class);
    }

    public function test_get_leasing_with_no_investors()
    {
        Notification::fake();
        config(['app.env' => 'local']); // Set environment for notification target

        $leasingData = [
            'vehicle_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'duration' => 365,
            'price' => 150.00,
        ];

        $result = $this->leasingService->getLeasing($leasingData);

        // Should still work, just no investor payments created
        $this->assertEquals(1, $result->vehicle_id);
        
        // Verify company payment was created
        $companyPayment = Payment::where('user_id', $this->companyUser->id)->first();
        $this->assertNotNull($companyPayment);

        // Verify no investor payments were created
        $investorPayments = Payment::where('operation_id', OperationType::I_LEASING)->count();
        $this->assertEquals(0, $investorPayments);

        // Notification should still be sent
        Notification::assertSentTo($this->adminUser, LeasingIncomeNotification::class);
    }

    public function test_invest_income_skips_users_without_contributions()
    {
        // Create investor without any contributions
        $investorWithoutContrib = User::factory()->create();
        $investorWithoutContrib->assignRole('investor');

        // Create investor with contributions
        $investorWithContrib = User::factory()->create();
        $investorWithContrib->assignRole('investor');
        // Create payment first, then contribution
        $payment = Payment::create([
            'user_id' => $investorWithContrib->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 1000.00,
            'confirmed' => true,
        ]);
        
        $investorWithContrib->contributions()->create([
            'payment_id' => $payment->id,
            'percents' => 1000000, // 100%
            'amount' => 1000.00,
        ]);

        $leasing = Leasing::create([
            'vehicle_id' => 1,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'duration' => 365,
            'price' => 200.00,
        ]);

        $investorCount = $this->leasingService->investIncome($leasing);

        // Should return count of 1 - only investors with contributions get payments
        $this->assertEquals(1, $investorCount);

        // But only create payment for investor with contributions
        $payments = Payment::where('operation_id', OperationType::I_LEASING)->get();
        $this->assertEquals(1, $payments->count());
        $this->assertEquals($investorWithContrib->id, $payments->first()->user_id);
    }
}