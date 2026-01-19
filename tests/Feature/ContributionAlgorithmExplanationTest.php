<?php

namespace Tests\Feature;

use App\Enums\OperationType;
use App\Models\User;
use App\Models\Payment;
use App\Models\Contribution;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionAlgorithmExplanationTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $paymentService;
    protected User $investor1;
    protected User $investor2;
    protected User $investor3;
    protected User $companyUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = app(PaymentService::class);
        
        // Create roles
        $this->createRoleIfNotExists('investor');
        $this->createRoleIfNotExists('company');
        
        // Create users
        $this->investor1 = User::factory()->create(['name' => 'Investor 1']);
        $this->investor1->assignRole('investor');
        
        $this->investor2 = User::factory()->create(['name' => 'Investor 2']);
        $this->investor2->assignRole('investor');
        
        $this->investor3 = User::factory()->create(['name' => 'Investor 3 (No Contributions)']);
        $this->investor3->assignRole('investor');
        
        $this->companyUser = User::factory()->create(['name' => 'Company User']);
        $this->companyUser->assignRole('company');
        
        // Create operations
        \App\Models\Operation::factory()->create(['id' => OperationType::CONTRIB->value, 'title' => 'Contribution', 'key' => 'contrib', 'description' => 'Investor contribution']);
        \App\Models\Operation::factory()->create(['id' => OperationType::INCOME->value, 'title' => 'Income', 'key' => 'income', 'description' => 'Investor income']);
        \App\Models\Operation::factory()->create(['id' => OperationType::REVENUE->value, 'title' => 'Revenue', 'key' => 'revenue', 'description' => 'Company revenue']);
    }

    /** @test */
    public function contribution_algorithm_explanation_with_detailed_tracking()
    {
        echo "\n=== CONTRIBUTION ALGORITHM EXPLANATION ===\n";
        echo "This test demonstrates why not all users get contribution records\n\n";
        
        // Initial state: No contributions exist
        echo "--- Initial State ---\n";
        $this->printContributionCounts();
        
        // Step 1: Investor 1 makes first contribution
        echo "\n--- Step 1: Investor 1 Contributes \$1000 ---\n";
        $this->paymentService->createPayment([
            'user_id' => $this->investor1->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 1000.00,
            'confirmed' => true,
        ]);
        
        echo "Expected: Investor 1 gets 1 contribution record (from createContribution)\n";
        echo "Expected: Other investors get 0 records (no percentage recalculation yet)\n";
        $this->printContributionCounts();
        $this->printContributionDetails();
        
        // Step 2: Investor 2 makes contribution (triggers percentage recalculation)
        echo "\n--- Step 2: Investor 2 Contributes \$500 ---\n";
        $this->paymentService->createPayment([
            'user_id' => $this->investor2->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 500.00,
            'confirmed' => true,
        ]);
        
        echo "Expected: Investor 2 gets 1 contribution record (from createContribution)\n";
        echo "Expected: Both investors get percentage recalculation records (from contributions method)\n";
        echo "Expected: Investor 3 still gets 0 records (never contributed)\n";
        $this->printContributionCounts();
        $this->printContributionDetails();
        
        // Step 3: Company revenue (should not create contributions)
        echo "\n--- Step 3: Company Revenue \$2000 (Should Not Create Contributions) ---\n";
        $this->paymentService->createPayment([
            'user_id' => $this->companyUser->id,
            'operation_id' => OperationType::REVENUE->value,
            'amount' => 2000.00,
            'confirmed' => true,
        ]);
        
        echo "Expected: No new contribution records (REVENUE doesn't affect investor balances)\n";
        $this->printContributionCounts();
        
        // Step 4: Income distribution (should create contributions for existing contributors only)
        echo "\n--- Step 4: Income Distribution \$300 to Investor 1 ---\n";
        $this->paymentService->createPayment([
            'user_id' => $this->investor1->id,
            'operation_id' => OperationType::INCOME->value,
            'amount' => 300.00,
            'confirmed' => true,
        ]);
        
        echo "Expected: Investor 1 gets 1 new contribution record (from createContribution)\n";
        echo "Expected: Both contributors get percentage recalculation records\n";
        echo "Expected: Investor 3 still gets 0 records (never contributed)\n";
        $this->printContributionCounts();
        $this->printContributionDetails();
        
        // Step 5: Now Investor 3 makes first contribution
        echo "\n--- Step 5: Investor 3 Makes First Contribution \$200 ---\n";
        $this->paymentService->createPayment([
            'user_id' => $this->investor3->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 200.00,
            'confirmed' => true,
        ]);
        
        echo "Expected: Investor 3 gets 1 contribution record (from createContribution)\n";
        echo "Expected: ALL THREE investors get percentage recalculation records\n";
        $this->printContributionCounts();
        $this->printContributionDetails();
        
        echo "\n=== ALGORITHM SUMMARY ===\n";
        echo "1. createContribution(): Creates 1 record for the payment's user\n";
        echo "2. contributions(): Creates percentage records for ALL users with existing contributions\n";
        echo "3. Users without contributions are excluded from percentage recalculations\n";
        echo "4. lastContribution->amount field is only updated by createContribution(), not contributions()\n";
    }
    
    private function printContributionCounts(): void
    {
        $count1 = Contribution::where('user_id', $this->investor1->id)->count();
        $count2 = Contribution::where('user_id', $this->investor2->id)->count();
        $count3 = Contribution::where('user_id', $this->investor3->id)->count();
        $countCompany = Contribution::where('user_id', $this->companyUser->id)->count();
        
        $this->investor1->refresh();
        $this->investor2->refresh();
        $this->investor3->refresh();
        
        echo "Contribution counts:\n";
        echo "  Investor 1: {$count1} records, lastContribution->amount: \${$this->investor1->lastContribution->amount}\n";
        echo "  Investor 2: {$count2} records, lastContribution->amount: \${$this->investor2->lastContribution->amount}\n";
        echo "  Investor 3: {$count3} records, lastContribution->amount: \${$this->investor3->lastContribution->amount}\n";
        echo "  Company: {$countCompany} records\n";
    }
    
    private function printContributionDetails(): void
    {
        $allContributions = Contribution::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        echo "Recent contributions (latest 10):\n";
        foreach ($allContributions as $contrib) {
            $paymentId = $contrib->payment_id ?? 'NULL';
            echo "  {$contrib->user->name}: Payment#{$paymentId}, Amount:\${$contrib->amount}, Percent:{$contrib->percents}\n";
        }
    }

    /** @test */
    public function algorithm_edge_case_user_with_zero_contributions()
    {
        echo "\n=== EDGE CASE: User With Zero Balance ===\n";
        
        // Investor 1 contributes then withdraws everything
        $this->paymentService->createPayment([
            'user_id' => $this->investor1->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 500.00,
            'confirmed' => true,
        ]);
        
        echo "After contribution:\n";
        $this->printContributionCounts();
        
        // Now investor 2 contributes (should trigger recalculation)
        $this->paymentService->createPayment([
            'user_id' => $this->investor2->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 300.00,
            'confirmed' => true,
        ]);
        
        echo "\nAfter second investor contribution:\n";
        $this->printContributionCounts();
        
        // The key insight: Even users with $0 lastContribution->amount get percentage records
        // if they have ANY contribution history (because they're in the contributions table)
    }
}