<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDean() || $user->isTeacher();
    }

    public function view(User $user, Teacher $teacher): bool
    {
        if ($user->isAdmin() || $user->isDean()) {
            return true;
        }

        // Преподаватель видит только свой профиль
        return $user->isTeacher() && $user->id === $teacher->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Teacher $teacher): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Teacher $teacher): bool
    {
        return $user->isAdmin();
    }
}
