<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $user->assignRole('investor');
            if ($user->email === config('services.users.user_admin.email')) {
                $user->assignRole('admin');
            }
            if ($user->email === config('services.users.user_operator.email')) {
                $user->assignRole('operator');
            }
        }
    }
}
