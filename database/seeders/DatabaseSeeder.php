<?php

namespace Database\Seeders;

use App\Models\User;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Operations');
        $this->call(OperationSeeder::class);

        $this->command->info('Users');
        $this->call(UserSeeder::class);

        $this->command->info('Roles');
        $this->call(RoleSeeder::class);

        $this->command->info('Permissions');
        $this->call(PermissionSeeder::class);

        $this->command->info('Role - Perms');
        $this->call(RolePermissionSeeder::class);

        $this->command->info('User Roles');
        $this->call(UserRolesSeeder::class);

        $this->command->info('Vehicles');
        $this->call(VehicleSeeder::class);

        $this->command->info('Vehicle - Payments');
        $this->call(PaymentSeeder::class);

        $this->command->info('Totals');
        $this->call(TotalSeeder::class);
    }
}
