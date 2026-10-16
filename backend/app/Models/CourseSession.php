<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSession extends Model
{
    protected $fillable = [
        'course_id',
        'room_id',
        'trainer_id',
        'date',
        'status',
        'session_info',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'course_session_id');
    }

    /**
     * Session start as a datetime (session date + course start time).
     */
    public function startsAt(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->course->start_time);
    }

    /**
     * Capacity cascade: first non-null wins
     * (session → course → session room → course room).
     */
    public function effectiveCapacity(): ?int
    {
        return $this->capacity
            ?? $this->course->capacity
            ?? $this->room?->capacity
            ?? $this->course->room?->capacity;
    }
}
