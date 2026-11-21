<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        // Список студентов: админ, декан, преподаватель.
        // Студент видит только себя — это через /auth/me + отдельные endpoint'ы.
        return $user->isAdmin() || $user->isDean() || $user->isTeacher();
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->isAdmin() || $user->isDean() || $user->isTeacher()) {
            return true;
        }

        // Студент видит только свой профиль.
        return $user->isStudent() && $user->id === $student->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }
}
