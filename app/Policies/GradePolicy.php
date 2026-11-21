<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDean() || $user->isTeacher();
    }

    public function view(User $user, Grade $grade): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDean()) {
            $facultyId = $grade->student?->group?->faculty_id;
            return $facultyId === $user->deanEmployee?->faculty_id;
        }

        if ($user->isTeacher()) {
            return $grade->teacher_id === $user->teacher?->id;
        }

        // Студент видит только свои
        if ($user->isStudent()) {
            return $grade->student?->user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, Grade $grade): bool
    {
        if ($grade->period?->isClosed()) {
            return false;
        }

        return $user->isTeacher() && $grade->teacher_id === $user->teacher?->id;
    }

    public function delete(User $user, Grade $grade): bool
    {
        if ($grade->period?->isClosed()) {
            return false;
        }

        return $user->isTeacher() && $grade->teacher_id === $user->teacher?->id;
    }
}
