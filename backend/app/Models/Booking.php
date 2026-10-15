<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'course_session_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    /**
     * Visibility scope: customers see only their own bookings, studio admins
     * only their studio's, admins everything. findOrFail() on top yields 404
     * for foreign bookings instead of 403.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::Admin) {
            return $query;
        }

        if ($user->role === UserRole::StudioAdmin) {
            return $query->whereHas('session.course', fn (Builder $q): Builder => $q->where('studio_id', $user->studio_id));
        }

        return $query->where('bookings.user_id', $user->id);
    }

    /**
     * Free cancellation up to 2 hours before the session starts.
     */
    public function isCancellable(): bool
    {
        return now()->lessThanOrEqualTo($this->session->startsAt()->subHours(2));
    }
}
