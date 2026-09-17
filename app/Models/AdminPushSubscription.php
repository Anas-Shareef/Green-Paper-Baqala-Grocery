<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminPushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'endpoint',
        'public_key',
        'auth_token',
        'device_name',
        'user_agent',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];
}
