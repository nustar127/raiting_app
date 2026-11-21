<?php

namespace App\Policies;

use App\Models\Discipline;
use App\Models\User;

class DisciplinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Discipline $discipline): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Discipline $discipline): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Discipline $discipline): bool
    {
        return $user->isAdmin();
    }
}
