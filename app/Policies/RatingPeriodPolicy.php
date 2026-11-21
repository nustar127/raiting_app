<?php

namespace App\Policies;

use App\Models\RatingPeriod;
use App\Models\User;

class RatingPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        // Студенты и преподаватели должны видеть активный период
        // (для отображения в UI и проверки, открыт ли ввод оценок).
        return true;
    }

    public function view(User $user, RatingPeriod $period): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, RatingPeriod $period): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, RatingPeriod $period): bool
    {
        // Удалять можно только закрытый период без данных.
        if ($period->isOpen()) {
            return false;
        }

        return $user->isAdmin();
    }

    public function open(User $user, RatingPeriod $period): bool
    {
        // Открывает декан своего факультета или админ.
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isDean()
            && $user->deanEmployee?->faculty_id === $period->faculty_id;
    }

    public function close(User $user, RatingPeriod $period): bool
    {
        return $this->open($user, $period);
    }
}
