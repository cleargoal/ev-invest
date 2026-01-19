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

        // Spatie Permission seeders removed - roles now stored directly in users table

        $this->command->info('Vehicles');
        $this->call(VehicleSeeder::class);

        $this->command->info('Vehicle - Payments');
        $this->call(PaymentSeeder::class);

        $this->command->info('Totals');
        $this->call(TotalSeeder::class);
    }
}
