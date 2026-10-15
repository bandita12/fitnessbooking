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

        if ($user->role === UserRole::StudioAdmin) {
            return $user->studio_id !== null && $user->studio_id === $studio->id;
        }

        if ($user->role === UserRole::Trainer) {
            return $user->studios()->whereKey($studio->id)->exists();
        }

        return false;
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
