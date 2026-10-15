<?php

namespace App\Models;

use App\Models\Concerns\ScopedByStudio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use ScopedByStudio;

    protected $fillable = [
        'studio_id',
        'trainer_id',
        'title',
        'description',
        'thumbnail_url',
        'image_url',
        'web_link',
        'day_of_week',
        'start_time',
        'end_time',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'capacity' => 'integer',
        ];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function leadTrainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class);
    }

    /**
     * Courses a trainer may lead or edit: studio-linked via the `studio_user`
     * pivot, or substitute on any session. findOrFail() on top yields 404 for
     * unassigned trainers instead of 403.
     */
    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->whereIn($this->getTable().'.studio_id', $user->studios()->pluck('studios.id'))
            ->orWhereHas('sessions', fn (Builder $s): Builder => $s->where('trainer_id', $user->id));
    }
}
