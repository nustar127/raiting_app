<?php

namespace App\Policies;

use App\Models\TeacherAssignment;
use App\Models\User;

class TeacherAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDean() || $user->isTeacher();
    }

    public function view(User $user, TeacherAssignment $assignment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDean()) {
            return $user->deanEmployee?->faculty_id
                === $assignment->curriculum->group->faculty_id;
        }

        if ($user->isTeacher()) {
            return $user->teacher?->id === $assignment->teacher_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, TeacherAssignment $assignment): bool
    {
        return false;
    }

    public function delete(User $user, TeacherAssignment $assignment): bool
    {
        return $user->isAdmin();
    }
}
