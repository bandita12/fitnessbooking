<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;

class StudioPolicy
{
    public function view(User $user, Studio $studio): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->studio_id !== null && $user->studio_id === $studio->id
            && in_array($user->role, [UserRole::StudioAdmin, UserRole::Trainer], true);
    }

    public function update(User $user, Studio $studio): bool
    {
        return $this->manages($user, $studio);
    }

    public function manageRooms(User $user, Studio $studio): bool
    {
        return $this->manages($user, $studio);
    }

    public function manageCourses(User $user, Studio $studio): bool
    {
        return $this->manages($user, $studio);
    }

    private function manages(User $user, Studio $studio): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::StudioAdmin
            && $user->studio_id !== null
            && $user->studio_id === $studio->id;
    }
}
