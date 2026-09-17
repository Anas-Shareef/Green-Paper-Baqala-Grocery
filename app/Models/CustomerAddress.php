<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'label',
        'villa_number',
        'building_number',
        'street_address',
        'zone',
        'landmark',
        'delivery_notes',
        'is_default',
    ];

    public function getIsDefaultAttribute($value): bool
    {
        return $value === true || $value === 't' || $value === 'true' || $value === 1 || $value === '1';
    }

    public function setIsDefaultAttribute($value): void
    {
        $this->attributes['is_default'] = ($value && $value !== 'false' && $value !== 'f') ? 'true' : 'false';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'address_id');
    }
}
