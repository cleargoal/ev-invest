<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Contribution;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseMissingContributions extends Command
{
    protected $signature = 'diagnose:missing-contributions {--investors=1,7 : Comma-separated list of investor IDs to investigate}';
    protected $description = 'Diagnose why specific investors are missing from recent contribution records';

    public function handle()
    {
        try {
            $this->info('=== MISSING CONTRIBUTIONS DIAGNOSTIC ===');
            
            $investorIds = explode(',', $this->option('investors'));
            $investorIds = array_map('trim', $investorIds);
            
            $this->info("Investigating investors: " . implode(', ', $investorIds));
            
            // Step 1: Get recent contribution records
            $this->newLine();
            $this->info('--- Step 1: Recent Contribution Records ---');
            
            $recentContributions = Contribution::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
                
            $this->table(
                ['ID', 'User', 'Payment ID', 'Amount', 'Percent', 'Created At'],
                $recentContributions->map(fn($c) => [
                    $c->id,
                    $c->user->name ?? 'Unknown',
                    $c->payment_id,
                    '$' . number_format($c->amount, 2),
                    number_format($c->percents, 2) . '%',
                    $c->created_at->format('Y-m-d H:i:s')
                ])
            );
            
            // Step 2: Investigate each missing investor
            foreach ($investorIds as $investorId) {
                $this->newLine();
                $this->info("--- Investigating Investor ID: {$investorId} ---");
                
                $user = User::find($investorId);
                if (!$user) {
                    $this->error("User with ID {$investorId} not found!");
                    continue;
                }
                
                $this->info("User: {$user->name}");
                $this->info("Email: {$user->email}");
                $this->info("Actual Contribution: \${$user->actual_contribution}");
                
                // Check contributions
                $contributionCount = Contribution::where('user_id', $investorId)->count();
                $this->info("Total Contributions: {$contributionCount}");
                
                if ($contributionCount > 0) {
                    $firstContrib = Contribution::where('user_id', $investorId)->orderBy('id', 'asc')->first();
                    $lastContrib = Contribution::where('user_id', $investorId)->orderBy('id', 'desc')->first();
                    
                    $this->info("First Contribution: ID#{$firstContrib->id}, Amount:\${$firstContrib->amount}, Date:{$firstContrib->created_at}");
                    $this->info("Last Contribution: ID#{$lastContrib->id}, Amount:\${$lastContrib->amount}, Date:{$lastContrib->created_at}");
                    
                    // Check recent contributions (last 24 hours)
                    $recentContribCount = Contribution::where('user_id', $investorId)
                        ->where('created_at', '>=', now()->subDay())
                        ->count();
                    $this->info("Recent Contributions (24h): {$recentContribCount}");
                }
                
                // Check payments
                $paymentCount = Payment::where('user_id', $investorId)->count();
                $this->info("Total Payments: {$paymentCount}");
                
                if ($paymentCount > 0) {
                    $recentPayments = Payment::where('user_id', $investorId)
                        ->where('created_at', '>=', now()->subDay())
                        ->with('operation')
                        ->get();
                        
                    $this->info("Recent Payments (24h): {$recentPayments->count()}");
                    foreach ($recentPayments as $payment) {
                        $opTitle = $payment->operation->title ?? 'Unknown';
                        $this->info("  Payment ID#{$payment->id}: {$opTitle}, \${$payment->amount}, {$payment->created_at}");
                    }
                }
                
                // Test whereHas relationship
                $foundByWhereHas = User::where('id', $investorId)->whereHas('contributions')->exists();
                $this->info("Found by whereHas('contributions'): " . ($foundByWhereHas ? 'YES' : 'NO'));
                
                // Test lastContribution relationship
                $user->load('lastContribution');
                $hasLastContrib = $user->lastContribution !== null;
                $this->info("lastContribution relationship works: " . ($hasLastContrib ? 'YES' : 'NO'));
                
                if ($hasLastContrib) {
                    $lastContrib = $user->lastContribution;
                    $this->info("Last Contribution via relationship: ID#{$lastContrib->id}, Amount:\${$lastContrib->amount}");
                }
                
                // Check if user would be included in contributions() method logic
                $wouldBeIncluded = User::where('id', $investorId)
                    ->whereHas('contributions')
                    ->with('lastContribution')
                    ->first();
                    
                if ($wouldBeIncluded && $wouldBeIncluded->lastContribution) {
                    $this->info("Would be included in contributions() method: YES");
                    $this->info("Last contribution amount for calculation: \${$wouldBeIncluded->lastContribution->amount}");
                } else {
                    $this->error("Would be included in contributions() method: NO");
                    $this->error("Reason: " . ($wouldBeIncluded ? 'No lastContribution' : 'Not found by whereHas'));
                }
            }
            
            // Step 3: Analyze recent bulk inserts
            $this->newLine();
            $this->info('--- Step 3: Recent Bulk Insert Analysis ---');
            
            // Find contributions created at the same time (likely from bulk insert)
            $bulkContributions = DB::select("
                SELECT created_at, COUNT(*) as count, GROUP_CONCAT(user_id) as user_ids
                FROM contributions 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY created_at
                HAVING count > 1
                ORDER BY created_at DESC
            ");
            
            if (empty($bulkContributions)) {
                $this->info("No bulk contributions found in last 24 hours");
            } else {
                foreach ($bulkContributions as $bulk) {
                    $this->info("Bulk insert at {$bulk->created_at}: {$bulk->count} records");
                    $this->info("  User IDs: {$bulk->user_ids}");
                    
                    $missingFromBulk = array_diff($investorIds, explode(',', $bulk->user_ids));
                    if (!empty($missingFromBulk)) {
                        $this->error("  Missing investors: " . implode(', ', $missingFromBulk));
                    }
                }
            }
            
            // Step 4: Check for data integrity issues
            $this->newLine();
            $this->info('--- Step 4: Data Integrity Check ---');
            
            foreach ($investorIds as $investorId) {
                $user = User::find($investorId);
                if (!$user) continue;
                
                // Check for orphaned contributions
                $orphanedContribs = Contribution::where('user_id', $investorId)
                    ->whereNotExists(function($query) {
                        $query->select(DB::raw(1))
                              ->from('payments')
                              ->whereColumn('payments.id', 'contributions.payment_id');
                    })
                    ->count();
                    
                if ($orphanedContribs > 0) {
                    $this->warn("Investor {$investorId}: {$orphanedContribs} orphaned contributions (no matching payment)");
                }
                
                // Check for contributions without payments
                $contribsWithoutPayments = Contribution::where('user_id', $investorId)
                    ->whereNull('payment_id')
                    ->count();
                    
                if ($contribsWithoutPayments > 0) {
                    $this->warn("Investor {$investorId}: {$contribsWithoutPayments} contributions without payment_id");
                }
                
                // Check actual_contribution vs latest contribution amount
                $latestContrib = Contribution::where('user_id', $investorId)->orderBy('id', 'desc')->first();
                if ($latestContrib && abs($user->actual_contribution - $latestContrib->amount) > 0.01) {
                    $this->warn("Investor {$investorId}: actual_contribution (\${$user->actual_contribution}) != latest contribution (\${$latestContrib->amount})");
                }
            }
            
            // Step 5: Simulate contributions() method
            $this->newLine();
            $this->info('--- Step 5: Simulate contributions() Method ---');
            
            $usersWithContributions = User::whereHas('contributions')
                ->with('lastContribution')
                ->get();
                
            $this->info("Users found by whereHas('contributions'): {$usersWithContributions->count()}");
            
            $totalAmount = $usersWithContributions->sum(function ($user) {
                return optional($user->lastContribution)->amount ?? 0;
            });
            
            $this->info("Total amount for percentage calculation: \${$totalAmount}");
            
            foreach ($usersWithContributions as $user) {
                $lastContrib = $user->lastContribution;
                $percent = $lastContrib && $totalAmount > 0 
                    ? ($lastContrib->amount / $totalAmount) * 100 
                    : 0;
                $this->info("  {$user->name} (ID:{$user->id}): \${$lastContrib->amount} ({$percent}%)");
            }
            
            $foundInvestorIds = $usersWithContributions->pluck('id')->toArray();
            $missingFromQuery = array_diff($investorIds, $foundInvestorIds);
            
            if (!empty($missingFromQuery)) {
                $this->error("Investors missing from whereHas query: " . implode(', ', $missingFromQuery));
            } else {
                $this->info("All investigated investors are found by the query");
            }
            
            $this->newLine();
            $this->info('=== DIAGNOSTIC COMPLETE ===');
            
        } catch (\Exception $e) {
            $this->error("Database connection error: " . $e->getMessage());
            $this->newLine();
            $this->info("=== MANUAL SQL INVESTIGATION QUERIES ===");
            $this->info("Run these queries directly in your database to investigate:");
            $this->newLine();
            
            foreach ($investorIds as $investorId) {
                $this->info("-- Investor {$investorId} Investigation --");
                $this->line("SELECT id, name, email, actual_contribution FROM users WHERE id = {$investorId};");
                $this->line("SELECT COUNT(*) as total_contributions FROM contributions WHERE user_id = {$investorId};");
                $this->line("SELECT id, amount, percents, created_at FROM contributions WHERE user_id = {$investorId} ORDER BY id DESC LIMIT 5;");
                $this->line("SELECT COUNT(*) as recent_contributions FROM contributions WHERE user_id = {$investorId} AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);");
                $this->newLine();
            }
            
            $this->info("-- Check for bulk inserts --");
            $this->line("SELECT created_at, COUNT(*) as count, GROUP_CONCAT(user_id) as user_ids FROM contributions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY created_at HAVING count > 1 ORDER BY created_at DESC;");
            $this->newLine();
            
            $this->info("-- Check contributions() method simulation --");
            $this->line("SELECT u.id, u.name, c.amount, c.percents FROM users u INNER JOIN contributions c ON u.id = c.user_id WHERE c.id IN (SELECT MAX(id) FROM contributions GROUP BY user_id) ORDER BY u.id;");
            $this->newLine();
            
            $this->info("Note: Run 'php artisan diagnose:missing-contributions' when database is accessible.");
        }
    }
}