<?php

namespace App\Models;

use App\Models\Concerns\ScopedByStudio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studio extends Model
{
    use ScopedByStudio;

    protected $fillable = [
        'name',
        'street',
        'zip',
        'city',
        'description',
        'thumbnail_url',
        'image_url',
        'web_link',
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * Trainers working for this studio (n:m via `studio_user`).
     */
    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'studio_user');
    }

    protected function tenantColumn(): string
    {
        return 'id';
    }
}
