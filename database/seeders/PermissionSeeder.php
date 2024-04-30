<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = ['create', 'update', 'confirm', 'sell', ];
        $models = ['vehicle', 'payment'];

        foreach ($permissions as $permission) {
            foreach ($models as $model) {
                if (
                    $model === 'vehicle' && $permission === 'confirm'
                    ||
                    $model === 'payment' && ($permission === 'sell' || $permission === 'update')
                ) {
                    continue;
                }
                $newPermission = new Permission();
                $newPermission->name = $permission . '-' . $model;
                $newPermission->save();
            }
        }
    }
}
