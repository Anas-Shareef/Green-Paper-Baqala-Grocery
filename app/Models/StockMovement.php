<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    // Canonical Movement Types (PRD Section 8)
    public const TYPE_PURCHASE_RECEIVED = 'purchase_received';
    public const TYPE_SALE = 'sale';
    public const TYPE_RESERVATION = 'reservation';
    public const TYPE_RESERVATION_RELEASE = 'reservation_release';
    public const TYPE_ADJUSTMENT_IN = 'adjustment_in';
    public const TYPE_ADJUSTMENT_OUT = 'adjustment_out';
    public const TYPE_DAMAGED = 'damaged';
    public const TYPE_EXPIRED = 'expired';
    public const TYPE_CUSTOMER_RETURN = 'customer_return';
    public const TYPE_SUPPLIER_RETURN = 'supplier_return';
    public const TYPE_STOCK_CORRECTION = 'stock_correction';
    public const TYPE_OPENING_STOCK = 'opening_stock';

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
