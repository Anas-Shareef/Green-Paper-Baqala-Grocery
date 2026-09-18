<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_receipt_id',
        'product_id',
        'barcode',
        'product_name',
        'quantity_expected',
        'quantity_received',
        'quantity_damaged',
        'quantity_sellable',
        'unit_cost',
        'discount',
        'tax_amount',
        'subtotal',
        'batch_number',
        'expiry_date',
        'notes',
    ];

    protected $casts = [
        'quantity_expected' => 'integer',
        'quantity_received' => 'integer',
        'quantity_damaged' => 'integer',
        'quantity_sellable' => 'integer',
        'unit_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'expiry_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
