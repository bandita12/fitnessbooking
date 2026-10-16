<?php

namespace App\Models;

use App\Models\Concerns\ScopedByStudio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasFactory, ScopedByStudio;

    protected $fillable = [
        'studio_id',
        'name',
        'capacity',
        'description',
        'thumbnail_url',
        'image_url',
        'color_code',
        'location_type',
        'lighting',
        'size_sqm',
        'width_meters',
        'length_meters',
        'height_meters',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'size_sqm' => 'integer',
        ];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }
}
