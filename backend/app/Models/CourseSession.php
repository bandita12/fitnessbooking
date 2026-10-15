<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
