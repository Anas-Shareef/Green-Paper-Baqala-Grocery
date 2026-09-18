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
        'ready_at',
        'failed_delivery_at',
        'failed_delivery_reason',
        'cod_collected_at',
        'cod_collected_by',
        'cod_collected_amount',
        'cod_difference_reason',
        'priority',
        'internal_notes',
        'delivery_notes',
        'picking_status',
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
        'cod_collected_amount' => 'decimal:2',
        'accepted_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_delivery_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cod_collected_at' => 'datetime',
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

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class)->orderBy('id', 'desc');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id', 'desc');
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    /**
     * Legacy Financial Accessor ($order->total -> $order->total_amount)
     */
    public function getTotalAttribute(): float
    {
        return (float) ($this->attributes['total_amount'] ?? 0.00);
    }

    /**
     * Operational Helpers & SLA Indicators
     */
    public function isAwaitingWhatsApp(): bool
    {
        return in_array($this->status, ['awaiting_whatsapp', 'pending']) && $this->order_source === 'PWA';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['delivered', 'cancelled', 'expired', 'returned', 'refunded']);
    }

    public function getElapsedMinutes(): int
    {
        return (int) round($this->created_at->diffInMinutes(now()));
    }

    public function isLate(int $slaMinutes = 45): bool
    {
        if ($this->isTerminal()) {
            return false;
        }
        return $this->getElapsedMinutes() > $slaMinutes;
    }

    public function getNextAction(): array
    {
        return match ($this->status) {
            'awaiting_whatsapp', 'pending' => [
                'action' => 'confirm',
                'label' => 'Confirm Order',
                'color' => 'emerald',
            ],
            'confirmed', 'accepted' => [
                'action' => 'prepare',
                'label' => 'Start Preparing',
                'color' => 'blue',
            ],
            'preparing' => [
                'action' => 'ready',
                'label' => 'Mark Ready',
                'color' => 'indigo',
            ],
            'ready' => [
                'action' => 'dispatch',
                'label' => $this->delivery_staff_id ? 'Mark Out for Delivery' : 'Assign Driver & Dispatch',
                'color' => 'purple',
            ],
            'out_for_delivery' => [
                'action' => 'deliver',
                'label' => 'Mark Delivered & Collect COD',
                'color' => 'emerald',
            ],
            'failed_delivery' => [
                'action' => 'retry',
                'label' => 'Retry Delivery',
                'color' => 'amber',
            ],
            default => [
                'action' => 'view',
                'label' => 'View Details',
                'color' => 'slate',
            ],
        };
    }
}

