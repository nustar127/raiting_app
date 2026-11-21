<?php

namespace App\Policies;

use App\Models\DeanEmployee;
use App\Models\User;

class DeanEmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDean();
    }

    public function view(User $user, DeanEmployee $deanEmployee): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Декан видит только своих коллег по факультету
        return $user->isDean()
            && $user->deanEmployee?->faculty_id === $deanEmployee->faculty_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, DeanEmployee $deanEmployee): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, DeanEmployee $deanEmployee): bool
    {
        return $user->isAdmin();
    }
}
