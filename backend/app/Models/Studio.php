<?php

namespace App\Models;

use App\Models\Concerns\ScopedByStudio;
use Illuminate\Database\Eloquent\Model;
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

    protected function tenantColumn(): string
    {
        return 'id';
    }
}
