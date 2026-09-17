<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_order_number',
        'customer_id',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'customer_villa',
        'customer_address',
        'customer_notes_snapshot',
        'subtotal',
        'discount_amount',
        'delivery_charge',
        'total_amount',
        'internal_delivery_cost',
        'product_cost',
        'gross_profit',
        'net_profit',
        'payment_method',
        'payment_status',
        'status',
        'whatsapp_status',
        'idempotency_key',
        'order_source',
        'delivery_staff_id',
        'notes',
        'accepted_at',
        'preparing_at',
        'out_for_delivery_at',
        'delivered_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'delivery_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'internal_delivery_cost' => 'decimal:2',
        'product_cost' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'accepted_at' => 'datetime',
        'preparing_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_staff_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }
}
