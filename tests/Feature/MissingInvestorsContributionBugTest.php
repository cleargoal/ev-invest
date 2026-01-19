<?php

namespace Tests\Feature;

use App\Enums\OperationType;
use App\Models\User;
use App\Models\Payment;
use App\Models\Contribution;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissingInvestorsContributionBugTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $paymentService;
    protected array $investors = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = app(PaymentService::class);
        
        // Create roles
        $this->createRoleIfNotExists('investor');
        $this->createRoleIfNotExists('company');
        
        // Create 7 investors to match your scenario
        for ($i = 1; $i <= 7; $i++) {
            $user = User::factory()->create(['name' => "Investor {$i}"]);
            $user->assignRole('investor');
            $this->investors[$i] = $user;
        }
        
        // Create operations
        \App\Models\Operation::factory()->create(['id' => OperationType::CONTRIB->value, 'title' => 'Contribution', 'key' => 'contrib', 'description' => 'Investor contribution']);
        \App\Models\Operation::factory()->create(['id' => OperationType::INCOME->value, 'title' => 'Income', 'key' => 'income', 'description' => 'Investor income']);
        \App\Models\Operation::factory()->create(['id' => OperationType::WITHDRAW->value, 'title' => 'Withdraw', 'key' => 'withdraw', 'description' => 'Withdrawal']);
    }

    /** @test */
    public function reproduce_missing_investors_in_contribution_recalculation()
    {
        echo "\n=== REPRODUCING MISSING INVESTORS BUG ===\n";
        
        // Step 1: Create initial contributions for investors 1, 2, 3, 5, 6, 7 (not 4)
        echo "\n--- Step 1: Initial Contributions ---\n";
        
        $initialContributions = [
            1 => 1000.00,
            2 => 500.00,
            3 => 750.00,
            5 => 300.00,
            6 => 450.00,
            7 => 200.00,
            // Investor 4 intentionally has no contributions
        ];
        
        foreach ($initialContributions as $investorNum => $amount) {
            $this->paymentService->createPayment([
                'user_id' => $this->investors[$investorNum]->id,
                'operation_id' => OperationType::CONTRIB->value,
                'amount' => $amount,
                'confirmed' => true,
            ]);
            echo "  Investor {$investorNum}: Contributed \${$amount}\n";
        }
        
        $this->printCurrentState("After initial contributions");
        
        // Step 2: Simulate some income operations that might affect certain investors
        echo "\n--- Step 2: Simulate Income Operations ---\n";
        
        // Give income to investor 2 (this should trigger percentage recalculation for ALL contributors)
        $this->paymentService->createPayment([
            'user_id' => $this->investors[2]->id,
            'operation_id' => OperationType::INCOME->value,
            'amount' => 100.00,
            'confirmed' => true,
        ]);
        
        echo "Gave \$100 income to Investor 2\n";
        $this->printCurrentState("After income to Investor 2");
        
        // Step 3: Check who got recalculation records
        echo "\n--- Step 3: Analyze Recalculation Records ---\n";
        
        $latestContributions = Contribution::orderBy('created_at', 'desc')
            ->limit(10)
            ->with('user')
            ->get();
            
        echo "Latest 10 contribution records:\n";
        foreach ($latestContributions as $contrib) {
            $userName = $contrib->user->name ?? 'Unknown';
            $paymentId = $contrib->payment_id;
            echo "  {$userName}: Payment#{$paymentId}, Amount:\${$contrib->amount}, Percent:{$contrib->percents}\n";
        }
        
        // Step 4: Investigate which users were loaded by the contributions() method
        echo "\n--- Step 4: Debug Users Loaded by contributions() Method ---\n";
        
        $usersWithContributions = User::whereHas('contributions')
            ->with('lastContribution')
            ->get();
            
        echo "Users found by whereHas('contributions'):\n";
        foreach ($usersWithContributions as $user) {
            $lastContrib = $user->lastContribution;
            $lastAmount = $lastContrib ? $lastContrib->amount : 'NULL';
            $lastId = $lastContrib ? $lastContrib->id : 'NULL';
            echo "  {$user->name}: Last contribution ID#{$lastId}, Amount:\${$lastAmount}\n";
        }
        
        // Step 5: Check lastContribution->amount values
        echo "\n--- Step 5: Check lastContribution->amount Values ---\n";
        
        foreach ($this->investors as $num => $investor) {
            $investor->refresh();
            $contributionCount = Contribution::where('user_id', $investor->id)->count();
            echo "  Investor {$num}: lastContribution->amount=\${$investor->lastContribution->amount}, total_records={$contributionCount}\n";
        }
        
        // Step 6: Identify the problem
        echo "\n--- Step 6: Problem Analysis ---\n";
        
        $expectedInvestors = array_keys($initialContributions);
        $actualInvestors = $usersWithContributions->pluck('name')->map(function($name) {
            return intval(str_replace('Investor ', '', $name));
        })->toArray();
        
        $missingInvestors = array_diff($expectedInvestors, $actualInvestors);
        
        if (!empty($missingInvestors)) {
            echo "PROBLEM FOUND: Missing investors: " . implode(', ', $missingInvestors) . "\n";
            
            foreach ($missingInvestors as $missingNum) {
                $investor = $this->investors[$missingNum];
                $hasContributions = Contribution::where('user_id', $investor->id)->exists();
                $lastContrib = Contribution::where('user_id', $investor->id)->orderBy('id', 'desc')->first();
                
                echo "  Investor {$missingNum}:\n";
                echo "    - Has contributions in DB: " . ($hasContributions ? 'YES' : 'NO') . "\n";
                echo "    - Last contribution ID: " . ($lastContrib ? $lastContrib->id : 'NULL') . "\n";
                echo "    - Last contribution amount: " . ($lastContrib ? $lastContrib->amount : 'NULL') . "\n";
                echo "    - whereHas('contributions') finds them: " . 
                    (User::where('id', $investor->id)->whereHas('contributions')->exists() ? 'YES' : 'NO') . "\n";
                echo "    - lastContribution relationship works: " . 
                    ($investor->lastContribution ? 'YES' : 'NO') . "\n";
            }
        } else {
            echo "No missing investors found in this test\n";
        }
    }
    
    private function printCurrentState(string $stage): void
    {
        echo "\n{$stage}:\n";
        foreach ($this->investors as $num => $investor) {
            $investor->refresh();
            $contributionCount = Contribution::where('user_id', $investor->id)->count();
            echo "  Investor {$num}: \${$investor->lastContribution->amount} ({$contributionCount} records)\n";
        }
    }

    /** @test */
    public function test_edge_cases_that_might_cause_missing_investors()
    {
        echo "\n=== TESTING EDGE CASES ===\n";
        
        // Edge case 1: Investor with zero balance (withdrew everything)
        echo "\n--- Edge Case 1: Investor with Zero Balance ---\n";
        
        // Investor 1: Contributes then withdraws everything
        $this->paymentService->createPayment([
            'user_id' => $this->investors[1]->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 500.00,
            'confirmed' => true,
        ]);
        
        $this->paymentService->createPayment([
            'user_id' => $this->investors[1]->id,
            'operation_id' => OperationType::WITHDRAW->value,
            'amount' => 500.00,
            'confirmed' => true,
        ]);
        
        // Investor 2: Normal contribution
        $this->paymentService->createPayment([
            'user_id' => $this->investors[2]->id,
            'operation_id' => OperationType::CONTRIB->value,
            'amount' => 300.00,
            'confirmed' => true,
        ]);
        
        $this->investors[1]->refresh();
        $this->investors[2]->refresh();
        
        echo "Investor 1 (zero balance): \${$this->investors[1]->lastContribution->amount}\n";
        echo "Investor 2 (normal): \${$this->investors[2]->lastContribution->amount}\n";
        
        // Trigger recalculation with income to investor 2
        $this->paymentService->createPayment([
            'user_id' => $this->investors[2]->id,
            'operation_id' => OperationType::INCOME->value,
            'amount' => 50.00,
            'confirmed' => true,
        ]);
        
        // Check if investor with zero balance is included in recalculation
        $usersWithContributions = User::whereHas('contributions')
            ->with('lastContribution')
            ->get();
            
        echo "Users included in recalculation:\n";
        foreach ($usersWithContributions as $user) {
            $lastContrib = $user->lastContribution;
            $amount = $lastContrib ? $lastContrib->amount : 'NULL';
            echo "  {$user->name}: \${$amount}\n";
        }
        
        // Both should be included even if one has zero balance
        $this->assertCount(2, $usersWithContributions, 'Both investors should be included even if one has zero balance');
    }
}