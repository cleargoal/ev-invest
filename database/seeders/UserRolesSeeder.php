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
        $roles = Role::all();

        foreach ($users as $user) {
            $user->assignRole('investor');
            if ($user->email === 'cleargoal01@gmail.com') {
                $user->assignRole('admin');
            }
            if ($user->email === 'luckynesvit@gmail.com') {
                $user->assignRole('operator');
            }
        }
    }
}
