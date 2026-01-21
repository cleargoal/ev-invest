<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class EnsureAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:ensure-admin
                          {--email= : Email address of the admin user}
                          {--name= : Name for new admin user}
                          {--password= : Password for new admin user}
                          {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure an admin user exists (create or upgrade existing user)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 1. Get/validate email
        $email = $this->option('email') ?: $this->ask('Email address');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email format');
            return self::FAILURE;
        }

        // 2. Check if user exists
        $user = User::where('email', $email)->first();

        if ($user) {
            return $this->upgradeExistingUser($user);
        }

        return $this->createNewAdmin($email);
    }

    /**
     * Upgrade an existing user to admin role
     */
    private function upgradeExistingUser(User $user): int
    {
        if ($user->hasRole('admin')) {
            $this->info("User {$user->email} is already an admin");
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("User exists with role '{$user->role}'. Upgrade to admin?")) {
                $this->info('Operation cancelled');
                return self::SUCCESS;
            }
        }

        $user->assignRole('admin');
        $this->info("✓ Upgraded {$user->email} to admin (ID: {$user->id})");
        $this->comment('  Password preserved');

        return self::SUCCESS;
    }

    /**
     * Create a new admin user
     */
    private function createNewAdmin(string $email): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $password = $this->option('password') ?: $this->secret('Password');

        if (!$this->option('password')) {
            $confirm = $this->secret('Confirm password');
            if ($password !== $confirm) {
                $this->error('Passwords do not match');
                return self::FAILURE;
            }
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info("✓ Created admin user {$user->email} (ID: {$user->id})");

        return self::SUCCESS;
    }
}
