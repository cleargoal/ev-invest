<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $roleOperator = Role::where('name', 'operator')->first();
        $operPermissions = Permission::where('name', 'like', '%-payment')->orWhere('name', 'like', '%-vehicle')->get();
        $roleOperator->syncPermissions($operPermissions);

        $roleInvestor = Role::where('name', 'investor')->first();
        $invPerms = Permission::where('name', 'create-payment')->first();
        $roleInvestor->givePermissionTo($invPerms);

    }
}
