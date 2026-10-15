<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function update(User $user, Course $course): bool
    {
        return $this->mayEdit($user, $course);
    }

    public function createUpdate(User $user, Course $course): bool
    {
        return $this->mayEdit($user, $course);
    }

    private function mayEdit(User $user, Course $course): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->studio_id === null || $user->studio_id !== $course->studio_id) {
            return false;
        }

        if ($user->role === UserRole::StudioAdmin) {
            return true;
        }

        if ($user->role !== UserRole::Trainer) {
            return false;
        }

        if ($course->trainer_id !== null && $course->trainer_id === $user->id) {
            return true;
        }

        return $course->sessions()->where('trainer_id', $user->id)->exists();
    }
}
