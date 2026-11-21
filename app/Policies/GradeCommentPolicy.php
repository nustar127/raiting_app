<?php

namespace App\Policies;

use App\Models\GradeComment;
use App\Models\User;

class GradeCommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDean() || $user->isTeacher() || $user->isStudent();
    }

    public function view(User $user, GradeComment $comment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $grade = $comment->grade;
        if (!$grade) {
            return false;
        }

        if ($user->isDean()) {
            return $grade->student?->group?->faculty_id === $user->deanEmployee?->faculty_id;
        }

        if ($user->isTeacher()) {
            return $comment->teacher_id === $user->teacher?->id;
        }

        if ($user->isStudent()) {
            return $grade->student?->user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, GradeComment $comment): bool
    {
        if ($comment->grade?->period?->isClosed()) {
            return false;
        }

        return $user->isTeacher() && $comment->teacher_id === $user->teacher?->id;
    }

    public function delete(User $user, GradeComment $comment): bool
    {
        return $this->update($user, $comment);
    }
}
