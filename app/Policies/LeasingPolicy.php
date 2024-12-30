<?php

namespace App\Policies;

use App\Models\Leasing;
use App\Models\User;

class LeasingPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
    }
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Leasing $leasing): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('company');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Leasing $leasing): bool
    {
        return $user->hasRole('company');
    }
}
