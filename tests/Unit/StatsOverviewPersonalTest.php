<?php

namespace Tests\Unit;

use App\Enums\OperationType;
use App\Filament\Investor\Widgets\StatsOverviewPersonal;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StatsOverviewPersonalTest extends TestCase
{
    use RefreshDatabase;

    private User $testUser;
    private User $otherInvestor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        $this->createRoleIfNotExists('investor');
        $this->createRoleIfNotExists('operator');
        $this->createRoleIfNotExists('admin');

        // Create test users
        $this->testUser = User::factory()->create();
        $this->testUser->assignRole('investor');
        
        $this->otherInvestor = User::factory()->create();
        $this->otherInvestor->assignRole('investor');

        // Create operator user (required by widget)
        $operatorUser = User::factory()->create();
        $operatorUser->assignRole('operator');

        // Authenticate the test user
        $this->actingAs($this->testUser);
    }

    /**
     * Helper method to access protected getStats method
     */
    private function getWidgetStats(): array
    {
        $widget = new StatsOverviewPersonal();
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        return $method->invoke($widget);
    }

    /**
     * Helper method to format currency like the widget does
     */
    private function formatCurrency(int $cents): string
    {
        return \Illuminate\Support\Number::format(round($cents / 100, 2), 2, locale: 'sv');
    }

    public function test_total_income_calculation()
    {
        // Create income payments for different dates
        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 100.00, // $100.00 (cast will convert to cents)
            'confirmed' => true,
            'created_at' => now()->subYears(2)
        ]);

        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::I_LEASING,
            'amount' => 50.00, // $50.00 (cast will convert to cents)
            'confirmed' => true,
            'created_at' => now()->subMonths(6)
        ]);

        $stats = $this->getWidgetStats();

        // Find the total income stat
        $totalIncomeStat = collect($stats)->first(function ($stat) {
            return str_contains($stat->getLabel(), 'Мій дохід за весь час, $$');
        });

        $this->assertNotNull($totalIncomeStat);
        // Should be $150.00 - if cast works, widget gets 150*100=15000 cents, then divides by 100 = 150
        // So widget shows: Number::format(round(15000 / 100, 2), 2, locale: 'sv') = "150,00"
        $this->assertEquals('150,00', $totalIncomeStat->getValue());
    }

    public function test_last_year_income_calculation()
    {
        // Create payments with different dates
        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 100.00,
            'confirmed' => true,
            'created_at' => now()->subYears(2) // Should NOT be included
        ]);

        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 80.00,
            'confirmed' => true,
            'created_at' => now()->subDays(200) // Should be included
        ]);

        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::I_LEASING,
            'amount' => 30.00,
            'confirmed' => true,
            'created_at' => now()->subDays(100) // Should be included
        ]);

        $stats = $this->getWidgetStats();

        // Find the last year income stat
        $lastYearIncomeStat = collect($stats)->first(function ($stat) {
            return str_contains($stat->getLabel(), 'Мій дохід за останній рік, $$');
        });

        $this->assertNotNull($lastYearIncomeStat);
        // Should be $110.00 - only last 365 days (80+30)
        $this->assertEquals('110,00', $lastYearIncomeStat->getValue());
    }

    public function test_current_year_income_calculation()
    {
        // Create payments with different years
        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 100.00,
            'confirmed' => true,
            'created_at' => Carbon::create(now()->year - 1, 12, 31) // Last year - should NOT be included
        ]);

        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 70.00,
            'confirmed' => true,
            'created_at' => Carbon::create(now()->year, 1, 15) // This year - should be included
        ]);

        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::I_LEASING,
            'amount' => 40.00,
            'confirmed' => true,
            'created_at' => Carbon::create(now()->year, 6, 15) // This year - should be included
        ]);

        $stats = $this->getWidgetStats();

        // Find the current year income stat
        $currentYearIncomeStat = collect($stats)->first(function ($stat) {
            return str_contains($stat->getLabel(), 'Мій дохід за поточний рік, $$');
        });

        $this->assertNotNull($currentYearIncomeStat);
        // Should be $110.00 - only current year (70+40)
        $this->assertEquals('110,00', $currentYearIncomeStat->getValue());
    }

    public function test_percentage_calculation()
    {
        // Create first contribution for test user
        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::FIRST,
            'amount' => 500.00, // $500.00
            'confirmed' => true,
        ]);

        // Create confirmed payments for pool calculation
        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::CONTRIB,
            'amount' => 300.00, // $300.00
            'confirmed' => true,
        ]);

        // Create confirmed payment for other investor
        Payment::factory()->create([
            'user_id' => $this->otherInvestor->id,
            'operation_id' => OperationType::FIRST,
            'amount' => 200.00, // $200.00
            'confirmed' => true,
        ]);

        $stats = $this->getWidgetStats();

        // Find the percentage stat
        $percentageStat = collect($stats)->first(function ($stat) {
            return str_contains($stat->getLabel(), 'Моя доля у сумі пулу (%)');
        });

        $this->assertNotNull($percentageStat);
        // Test user: $800, Other: $200, Total: $1000
        // Test user percentage: 80000/100000 = 80%
        // With the formula: round(80000 * 1000000 / 100000) / 10000 = 80.00
        $this->assertEquals('80,00', $percentageStat->getValue());
    }

    public function test_income_growth_calculation()
    {
        // Create first contribution
        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::FIRST,
            'amount' => 1000.00, // $1000.00
            'confirmed' => true,
        ]);

        // Create income payments
        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 250.00, // $250.00
            'confirmed' => true,
        ]);

        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::I_LEASING,
            'amount' => 250.00, // $250.00
            'confirmed' => true,
        ]);

        $stats = $this->getWidgetStats();

        // Find the growth percentage stat
        $growthStat = collect($stats)->first(function ($stat) {
            return str_contains($stat->getLabel(), 'Мій дохід за весь час, %%');
        });

        $this->assertNotNull($growthStat);
        // Total income: $500, First contribution: $1000
        // Growth: 50000/100000 = 0.5, displayed as 50,00
        $this->assertEquals('50,00', $growthStat->getValue());
    }

    public function test_different_date_scenarios_are_different()
    {
        // Create payments across different time periods
        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 100.00,
            'confirmed' => true,
            'created_at' => now()->subYears(2) // Only in total
        ]);

        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 50.00,
            'confirmed' => true,
            'created_at' => now()->subDays(400) // In total only (more than 365 days ago)
        ]);

        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 30.00,
            'confirmed' => true,
            'created_at' => Carbon::create(now()->year, 3, 15) // In total, last year, and current year
        ]);

        Payment::factory()->create([
            'user_id' => $this->testUser->id,
            'operation_id' => OperationType::INCOME,
            'amount' => 20.00,
            'confirmed' => true,
            'created_at' => Carbon::create(now()->year - 1, 11, 15) // Last year, within 365 days, but NOT current calendar year
        ]);

        $stats = $this->getWidgetStats();

        $totalIncome = collect($stats)->first(fn($stat) => 
            str_contains($stat->getLabel(), 'Мій дохід за весь час, $$'))->getValue();
        
        $lastYearIncome = collect($stats)->first(fn($stat) => 
            str_contains($stat->getLabel(), 'Мій дохід за останній рік, $$'))->getValue();
        
        $currentYearIncome = collect($stats)->first(fn($stat) => 
            str_contains($stat->getLabel(), 'Мій дохід за поточний рік, $$'))->getValue();

        // The main test: ensure they are actually different from each other (the bug was they were all the same)
        $this->assertNotEquals($totalIncome, $lastYearIncome, 'Total income should be different from last year income');
        $this->assertNotEquals($lastYearIncome, $currentYearIncome, 'Last year income should be different from current year income');
        $this->assertNotEquals($totalIncome, $currentYearIncome, 'Total income should be different from current year income');
        
        // Additional verification: total should be highest, then last year, then current year
        $totalValue = (float) str_replace([' ', ','], ['', '.'], $totalIncome);
        $lastYearValue = (float) str_replace([' ', ','], ['', '.'], $lastYearIncome);
        $currentYearValue = (float) str_replace([' ', ','], ['', '.'], $currentYearIncome);
        
        $this->assertGreaterThan($lastYearValue, $totalValue, 'Total income should be greater than last year');
        $this->assertGreaterThan($currentYearValue, $lastYearValue, 'Last year income should be greater than current year');
    }
}