<?php

namespace App\Policies;

use App\Models\Faculty;
use App\Models\User;

class FacultyPolicy
{
    public function viewAny(User $user): bool
    {
        // Видят все авторизованные — справочник нужен и студенту.
        return true;
    }

    public function view(User $user, Faculty $faculty): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Faculty $faculty): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Faculty $faculty): bool
    {
        return $user->isAdmin();
    }
}
