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
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool === null) {
            $bool = (bool) $value;
        }

        $driver = config('database.default');
        $connDriver = config("database.connections.{$driver}.driver", $driver);

        if ($connDriver === 'sqlite') {
            $this->attributes['is_read'] = $bool ? 1 : 0;
        } else {
            $this->attributes['is_read'] = $bool ? 'true' : 'false';
        }
    }

    public function scopeUnread($query)
    {
        $driver = config('database.default');
        $connDriver = config("database.connections.{$driver}.driver", $driver);

        if ($connDriver === 'sqlite') {
            return $query->where(function ($q) {
                $q->where('is_read', 0)
                  ->orWhere('is_read', false)
                  ->orWhere('is_read', 'false')
                  ->orWhere('is_read', 'f');
            });
        }

        return $query->where('is_read', false);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
