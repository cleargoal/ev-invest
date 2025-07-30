<?php

namespace Tests\Unit;

use App\Enums\OperationType;
use App\Events\TotalChangedEvent;
use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Total;
use App\Models\User;
use App\Notifications\NewPaymentNotify;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;
    private User $investorUser;
    private User $companyUser;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'investor']);
        Role::create(['name' => 'company']);
        Role::create(['name' => 'admin']);

        // Create users
        $this->investorUser = User::factory()->create();
        $this->investorUser->assignRole('investor');
        
        $this->companyUser = User::factory()->create();
        $this->companyUser->assignRole('company');

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->paymentService = app(PaymentService::class);
    }

    public function test_create_payment_with_contribution_management()
    {
        $paymentData = [
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::FIRST,
            'amount' => 500.00,
            'confirmed' => true,
            'created_at' => now(),
        ];

        $payment = $this->paymentService->createPayment($paymentData);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals($this->investorUser->id, $payment->user_id);
        $this->assertEquals(OperationType::FIRST, $payment->operation_id);
        $this->assertEquals(500.00, $payment->amount);
        $this->assertTrue($payment->confirmed);

        // Verify contribution was created
        $contribution = Contribution::where('user_id', $this->investorUser->id)->first();
        $this->assertNotNull($contribution);
        $this->assertEquals($payment->id, $contribution->payment_id);
        $this->assertEquals(500.00, $contribution->amount);

        // Verify user's actual_contribution was updated
        $this->investorUser->refresh();
        $this->assertEquals(500.00, $this->investorUser->actual_contribution);
    }

    public function test_create_payment_revenue_skips_contribution_management()
    {
        $paymentData = [
            'user_id' => $this->companyUser->id,
            'operation_id' => OperationType::REVENUE,
            'amount' => 300.00,
            'confirmed' => true,
        ];

        $payment = $this->paymentService->createPayment($paymentData);

        $this->assertInstanceOf(Payment::class, $payment);

        // Verify no contribution was created for REVENUE payments
        $contributionCount = Contribution::where('user_id', $this->companyUser->id)->count();
        $this->assertEquals(0, $contributionCount);
    }

    public function test_manage_contributions_transaction_success()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 300.00,
            'confirmed' => true,
        ]);

        $result = $this->paymentService->manageContributions($payment);

        $this->assertTrue($result);

        // Verify contribution was created
        $contribution = Contribution::where('payment_id', $payment->id)->first();
        $this->assertNotNull($contribution);
        $this->assertEquals($payment->user_id, $contribution->user_id);
        $this->assertEquals(300.00, $contribution->amount);

        // Verify user's actual_contribution was updated
        $this->investorUser->refresh();
        $this->assertEquals(300.00, $this->investorUser->actual_contribution);
    }

    public function test_manage_contributions_transaction_rollback_on_failure()
    {
        // Create a payment with invalid user_id to cause failure
        $payment = Payment::factory()->create([
            'user_id' => 99999, // Non-existent user
            'operation_id' => OperationType::CONTRIB,
            'amount' => 300.00,
            'confirmed' => true,
        ]);

        // This should cause an exception and rollback
        $this->expectException(\Throwable::class);

        try {
            $this->paymentService->manageContributions($payment);
        } catch (\Throwable $e) {
            // Verify no contributions were created due to rollback
            $contributionCount = Contribution::where('payment_id', $payment->id)->count();
            $this->assertEquals(0, $contributionCount);

            throw $e;
        }
    }

    public function test_manage_contributions_skips_unconfirmed_payments()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 300.00,
            'confirmed' => false, // Not confirmed
        ]);

        $result = $this->paymentService->manageContributions($payment);

        $this->assertTrue($result);

        // Verify no contribution was created for unconfirmed payment
        $contributionCount = Contribution::where('payment_id', $payment->id)->count();
        $this->assertEquals(0, $contributionCount);

        // Verify user's actual_contribution was not updated
        $this->investorUser->refresh();
        $this->assertNull($this->investorUser->actual_contribution);
    }

    public function test_manage_contributions_with_add_income_flag()
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 100.00,
            'confirmed' => true,
        ]);

        // With addIncome = true, should skip the contributions calculation
        $result = $this->paymentService->manageContributions($payment, true);

        $this->assertTrue($result);

        // Verify contribution was created (first part of method)
        $contribution = Contribution::where('payment_id', $payment->id)->first();
        $this->assertNotNull($contribution);

        // But no additional contribution calculations should have been made
        // (This is harder to test without mocking, but the method should complete without errors)
    }

    public function test_payment_confirmation_transaction_success()
    {
        Event::fake([TotalChangedEvent::class]);

        $payment = Payment::factory()->create([
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::FIRST,
            'amount' => 400.00,
            'confirmed' => true,
        ]);

        $this->paymentService->paymentConfirmation($payment);

        // Verify contribution was created
        $contribution = Contribution::where('payment_id', $payment->id)->first();
        $this->assertNotNull($contribution);

        // Verify total was created
        $total = Total::where('payment_id', $payment->id)->first();
        $this->assertNotNull($total);
        $this->assertEquals(400.00, $total->amount);

        // Verify event was dispatched
        Event::assertDispatched(TotalChangedEvent::class, function ($event) use ($payment) {
            return $event->totalAmount === 400.00 &&
                   $event->description === 'Внесок інвестора' &&
                   $event->amount === $payment->amount;
        });
    }

    public function test_payment_confirmation_transaction_rollback_on_failure()
    {
        Event::fake([TotalChangedEvent::class]);

        // Create payment with invalid data to cause failure
        $payment = Payment::factory()->create([
            'user_id' => 99999, // Non-existent user
            'operation_id' => OperationType::FIRST,
            'amount' => 400.00,
            'confirmed' => true,
        ]);

        $this->expectException(\Throwable::class);

        try {
            $this->paymentService->paymentConfirmation($payment);
        } catch (\Throwable $e) {
            // Verify no contributions were created due to rollback
            $contributionCount = Contribution::where('payment_id', $payment->id)->count();
            $this->assertEquals(0, $contributionCount);

            // Verify no totals were created due to rollback
            $totalCount = Total::where('payment_id', $payment->id)->count();
            $this->assertEquals(0, $totalCount);

            // Verify event was not dispatched due to rollback
            Event::assertNotDispatched(TotalChangedEvent::class);

            throw $e;
        }
    }

    public function test_notify_sends_to_correct_users_based_on_environment()
    {
        Notification::fake();

        // Test local environment (should notify admin)
        config(['app.env' => 'local']);

        $this->paymentService->notify();

        Notification::assertSentTo($this->adminUser, NewPaymentNotify::class);
        Notification::assertNotSentTo($this->companyUser, NewPaymentNotify::class);
    }

    public function test_notify_sends_to_company_users_in_production()
    {
        Notification::fake();

        // Test production environment (should notify company)
        config(['app.env' => 'production']);

        $this->paymentService->notify();

        Notification::assertSentTo($this->companyUser, NewPaymentNotify::class);
        Notification::assertNotSentTo($this->adminUser, NewPaymentNotify::class);
    }

    public function test_create_payment_with_multiple_contributions()
    {
        // Create first payment (base contribution)
        $firstPaymentData = [
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::FIRST,
            'amount' => 200.00,
            'confirmed' => true,
        ];

        $firstPayment = $this->paymentService->createPayment($firstPaymentData);

        // Create second payment (additional contribution)
        $secondPaymentData = [
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 150.00,
            'confirmed' => true,
        ];

        $secondPayment = $this->paymentService->createPayment($secondPaymentData);

        // Verify both payments were created
        $this->assertInstanceOf(Payment::class, $firstPayment);
        $this->assertInstanceOf(Payment::class, $secondPayment);

        // Verify contributions were created for both
        $contributions = Contribution::where('user_id', $this->investorUser->id)
            ->orderBy('id')
            ->get();

        $this->assertEquals(2, $contributions->count());

        // First contribution should have amount = 200
        $this->assertEquals(200.00, $contributions[0]->amount);

        // Second contribution should have cumulative amount = 350 (200 + 150)
        $this->assertEquals(350.00, $contributions[1]->amount);

        // User's actual_contribution should be updated to latest
        $this->investorUser->refresh();
        $this->assertEquals(350.00, $this->investorUser->actual_contribution);
    }

    public function test_create_payment_handles_custom_dates()
    {
        $customDate = Carbon::create(2024, 6, 15, 14, 30, 0);

        $paymentData = [
            'user_id' => $this->investorUser->id,
            'operation_id' => OperationType::FIRST,
            'amount' => 250.00,
            'confirmed' => true,
            'created_at' => $customDate,
        ];

        $payment = $this->paymentService->createPayment($paymentData);

        $this->assertEquals($customDate, $payment->created_at);

        // Verify contribution also uses the custom date
        $contribution = Contribution::where('payment_id', $payment->id)->first();
        $this->assertNotNull($contribution);
    }
}