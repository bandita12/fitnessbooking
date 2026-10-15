<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Course;
use App\Models\Studio;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Policies\CoursePolicy;
use App\Policies\StudioPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Studio::class => StudioPolicy::class,
        Course::class => CoursePolicy::class,
        Booking::class => BookingPolicy::class,
    ];

    public function boot(): void
    {
        Gate::before(fn (User $user, string $ability): ?bool => $user->role === UserRole::Admin ? true : null);

        Gate::define('studios.manage', fn (User $user): bool => $user->role === UserRole::Admin);
        Gate::define('users.assign-roles', fn (User $user): bool => $user->role === UserRole::Admin);
        Gate::define('studios.update', fn (User $user): bool => in_array($user->role, [UserRole::Admin, UserRole::StudioAdmin], true));
        Gate::define('rooms.manage', fn (User $user): bool => in_array($user->role, [UserRole::Admin, UserRole::StudioAdmin], true));
        Gate::define('courses.manage', fn (User $user): bool => in_array($user->role, [UserRole::Admin, UserRole::StudioAdmin], true));
        Gate::define('course-updates.create', fn (User $user): bool => in_array($user->role, [UserRole::Admin, UserRole::StudioAdmin, UserRole::Trainer], true));
        Gate::define('courses.view', fn (User $user): bool => true);
        Gate::define('bookings.manage', fn (User $user): bool => true);
    }
}
