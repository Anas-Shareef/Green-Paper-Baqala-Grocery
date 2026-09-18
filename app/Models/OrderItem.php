<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'quantity',
        'picked_quantity',
        'item_status',
        'unit_price',
        'wholesale_cost',
        'total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'wholesale_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer',
        'picked_quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
