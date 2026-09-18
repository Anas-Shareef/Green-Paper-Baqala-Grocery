<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StockReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_number',
        'supplier_id',
        'supplier_name_snapshot',
        'supplier_invoice_number',
        'invoice_date',
        'purchase_reference',
        'receiving_date',
        'status',
        'subtotal',
        'discount',
        'tax_amount',
        'other_charges',
        'total_amount',
        'payment_status',
        'notes',
        'attachment_url',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'receiving_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class, 'stock_receipt_id');
    }

    /**
     * Generate the next unique GRN Number: GRN-000001, GRN-000002, etc.
     */
    public static function generateNextGrnNumber(): string
    {
        $lastId = DB::table('stock_receipts')->max('id') ?? 0;
        $nextNum = $lastId + 1;
        return sprintf('GRN-%06d', $nextNum);
    }
}
