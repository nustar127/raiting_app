<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkType;

class WorkTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkType $workType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, WorkType $workType): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, WorkType $workType): bool
    {
        return $user->isAdmin();
    }
}
