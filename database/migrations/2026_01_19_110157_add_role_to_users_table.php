<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add role column to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('investor')->after('email');
            $table->index('role');
        });

        // Populate role column from existing model_has_roles table
        // This ensures backward compatibility during transition
        if (Schema::hasTable('model_has_roles')) {
            $userRoles = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_type', 'App\\Models\\User')
                ->select('model_has_roles.model_id as user_id', 'roles.name as role_name')
                ->get()
                ->groupBy('user_id');

            foreach ($userRoles as $userId => $roles) {
                // Take the first role if user has multiple roles
                $roleName = $roles->first()->role_name;
                DB::table('users')
                    ->where('id', $userId)
                    ->update(['role' => $roleName]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
