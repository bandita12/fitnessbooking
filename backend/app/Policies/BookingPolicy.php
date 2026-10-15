<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $this->mayAccess($user, $booking);
    }

    /**
     * Who may cancel at all. The 2-hour window is checked separately in the
     * controller via Booking::isCancellable() so a late cancellation yields
     * 422 (validation) instead of 403 (forbidden).
     */
    public function delete(User $user, Booking $booking): bool
    {
        return $this->mayAccess($user, $booking);
    }

    private function mayAccess(User $user, Booking $booking): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Customer) {
            return $booking->user_id === $user->id;
        }

        if ($user->role === UserRole::StudioAdmin) {
            return $user->studio_id !== null
                && $user->studio_id === $booking->session->course->studio_id;
        }

        return false;
    }
}
