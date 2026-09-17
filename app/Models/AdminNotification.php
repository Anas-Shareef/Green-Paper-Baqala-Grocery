<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'message',
        'order_id',
        'order_number',
        'is_read',
    ];

    public function getIsReadAttribute($value): bool
    {
        return $value === true || $value === 't' || $value === 'true' || $value === 1 || $value === '1';
    }

    public function setIsReadAttribute($value): void
    {
        $this->attributes['is_read'] = ($value && $value !== 'false' && $value !== 'f') ? 'true' : 'false';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
