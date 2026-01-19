<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

        foreach ($users as $user) {
            User::factory()->create([
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ]);
        }
    }
}
