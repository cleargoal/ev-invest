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
                'name' => 'Володимир Єфремов',
                'email' => 'cleargoal01@gmail.com',
            ],
            [
                'name' => 'Володимир Несвіт',
                'email' => 'luckynesvit@gmail.com',
            ],
            [
                'name' => 'Євген Кауров',
                'email' => 'Eugene.kaurov@gmail.com',
            ],
        ];

        foreach ($users as $user) {
            User::factory()->create([
                'name' => $user['name'],
                'email' => $user['email'],
            ]);
        }
    }
}
