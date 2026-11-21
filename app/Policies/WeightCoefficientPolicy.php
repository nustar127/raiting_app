<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeightCoefficient;

class WeightCoefficientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WeightCoefficient $weight): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, WeightCoefficient $weight): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, WeightCoefficient $weight): bool
    {
        return $user->isAdmin();
    }

    public function approve(User $user, WeightCoefficient $weight): bool
    {
        return $user->isAdmin();
    }
}
