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
        Role::create(['name' => 'company']);
        Role::create(['name' => 'investor']);
        Role::create(['name' => 'admin']);

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

        // Create investor contribution for income calculation
        $this->investorUser->contributions()->create([
            'payment_id' => 1,
            'percents' => 500000, // 50% share
            'amount' => 500.00,
        ]);

        $leasingData = [
            'title' => 'Car Lease Income',
            'price' => 200.00, // $200 leasing income
            'description' => 'Monthly lease payment',
        ];

        $result = $this->leasingService->getLeasing($leasingData);

        // Verify leasing record was created
        $this->assertInstanceOf(Leasing::class, $result);
        $this->assertEquals('Car Lease Income', $result->title);
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

        // Create investor contribution
        $this->investorUser->contributions()->create([
            'payment_id' => 1,
            'percents' => 500000,
            'amount' => 500.00,
        ]);

        // Mock a database failure by deleting the company user
        $this->companyUser->delete();

        $leasingData = [
            'title' => 'Car Lease Income',
            'price' => 200.00,
            'description' => 'Monthly lease payment',
        ];

        // Expect an exception to be thrown
        $this->expectException(\Throwable::class);

        try {
            $this->leasingService->getLeasing($leasingData);
        } catch (\Throwable $e) {
            // Verify that no leasing record was created due to transaction rollback
            $this->assertEquals(0, Leasing::count());

            // Verify no payments were created
            $this->assertEquals(0, Payment::count());

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
            'title' => 'Test Leasing',
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
        $investor1->contributions()->create([
            'payment_id' => 1,
            'percents' => 700000, // 70%
            'amount' => 700.00,
        ]);

        $investor2 = User::factory()->create();
        $investor2->assignRole('investor');
        $investor2->contributions()->create([
            'payment_id' => 2,
            'percents' => 300000, // 30%
            'amount' => 300.00,
        ]);

        $leasing = Leasing::create([
            'title' => 'Test Income Distribution',
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

        // Create investor contribution
        $this->investorUser->contributions()->create([
            'payment_id' => 1,
            'percents' => 1000000, // 100% share
            'amount' => 1000.00,
        ]);

        $customDate = Carbon::create(2024, 1, 15, 10, 30, 0);
        $leasingData = [
            'title' => 'Custom Date Lease',
            'price' => 300.00,
            'description' => 'Test with custom date',
            'created_at' => $customDate,
        ];

        $result = $this->leasingService->getLeasing($leasingData);

        $this->assertEquals($customDate, $result->created_at);

        // Verify payments have the same custom date
        $companyPayment = Payment::where('user_id', $this->companyUser->id)->first();
        $investorPayment = Payment::where('user_id', $this->investorUser->id)->first();

        $this->assertEquals($customDate, $companyPayment->created_at);
        $this->assertEquals($customDate, $investorPayment->created_at);
    }

    public function test_notification_sent_to_correct_users_based_on_environment()
    {
        Notification::fake();

        // Test local environment (should notify admin)
        config(['app.env' => 'local']);

        $this->investorUser->contributions()->create([
            'payment_id' => 1,
            'percents' => 500000,
            'amount' => 500.00,
        ]);

        $leasingData = [
            'title' => 'Test Lease',
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

        $leasingData = [
            'title' => 'No Investors Lease',
            'price' => 150.00,
            'description' => 'Test with no investors',
        ];

        $result = $this->leasingService->getLeasing($leasingData);

        // Should still work, just no investor payments created
        $this->assertEquals('No Investors Lease', $result->title);
        
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
        $investorWithContrib->contributions()->create([
            'payment_id' => 1,
            'percents' => 1000000, // 100%
            'amount' => 1000.00,
        ]);

        $leasing = Leasing::create([
            'title' => 'Test Skip Users',
            'price' => 200.00,
        ]);

        $investorCount = $this->leasingService->investIncome($leasing);

        // Should return count of all users (even those without contributions)
        $this->assertEquals(2, $investorCount);

        // But only create payment for investor with contributions
        $payments = Payment::where('operation_id', OperationType::I_LEASING)->get();
        $this->assertEquals(1, $payments->count());
        $this->assertEquals($investorWithContrib->id, $payments->first()->user_id);
    }
}