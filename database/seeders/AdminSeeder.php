<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('services.users.user_admin.email');
        $name = config('services.users.user_admin.name');

        // Check if user already exists
        $existingUser = User::where('email', $email)->first();

        $data = [
            'name' => $name,
            'role' => 'admin',
        ];

        // Only set password for new users
        if (!$existingUser) {
            $data['password'] = Hash::make('password');
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            $data
        );

        $this->command->info(
            $existingUser
                ? "✓ Upgraded {$email} to admin role (ID: {$user->id})"
                : "✓ Created admin user {$email} (ID: {$user->id})"
        );

        if (!$existingUser) {
            $this->command->warn('  Default password: password');
        }
    }
}
