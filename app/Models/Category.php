<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'image',
        'description',
        'sort_order',
        'status',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        $img = $this->attributes['image'] ?? null;
        if (empty($img)) {
            return null;
        }
        if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
            return $img;
        }
        return asset('storage/' . ltrim($img, '/'));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
