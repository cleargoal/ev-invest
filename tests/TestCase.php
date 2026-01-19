<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup that runs before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clear Spatie permission cache to prevent stale data issues
        if (app()->has('permission.cache.store')) {
            app('permission.cache.store')->flush();
        }

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Create a role if it doesn't exist
     *
     * Note: Uses firstOrCreate without transaction to avoid deadlocks
     * when used with RefreshDatabase trait
     */
    protected function createRoleIfNotExists(string $roleName): Role
    {
        // Simple firstOrCreate - no transaction needed since RefreshDatabase
        // handles test isolation via database transactions
        return Role::firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web']
        );
    }

    /**
     * Create standard roles for testing
     */
    protected function createStandardRoles(): void
    {
        $this->createRoleIfNotExists('admin');
        $this->createRoleIfNotExists('operator');
        $this->createRoleIfNotExists('investor');
        $this->createRoleIfNotExists('company');
    }
}
