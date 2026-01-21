<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => config('services.users.user_admin.name'),
                'email' => config('services.users.user_admin.email'),
                'role' => 'admin',
            ],
            [
                'name' => config('services.users.user_operator.name'),
                'email' => config('services.users.user_operator.email'),
                'role' => 'operator',
            ],
            [
                'name' => config('services.users.user_investor.name'),
                'email' => config('services.users.user_investor.email'),
                'role' => 'investor',
            ],
            [
                'name' => config('services.users.user_company.name'),
                'email' => config('services.users.user_company.email'),
                'role' => 'company',
            ],
        ];

        foreach ($users as $userData) {
            // Check if user exists
            $existingUser = User::where('email', $userData['email'])->first();

            $data = [
                'name' => $userData['name'],
                'role' => $userData['role'],
            ];

            // Only set password for new users
            if (!$existingUser) {
                $data['password'] = Hash::make('password');
            }

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $data
            );

            $this->command->info(
                $existingUser
                    ? "Updated: {$userData['email']} (ID: {$user->id})"
                    : "Created: {$userData['email']} (ID: {$user->id})"
            );
        }
    }
}
