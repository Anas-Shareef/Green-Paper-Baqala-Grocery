<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'barcode',
        'sku',
        'name',
        'brand',
        'unit',
        'wholesale_cost',
        'retail_price',
        'stock_quantity',
        'reserved_quantity',
        'minimum_stock_level',
        'maximum_stock_level',
        'expiry_date',
        'supplier_name',
        'image',
        'description',
        'status',
    ];

    protected $hidden = [
        'wholesale_cost',
    ];

    protected $casts = [
        'wholesale_cost' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'minimum_stock_level' => 'integer',
        'maximum_stock_level' => 'integer',
        'expiry_date' => 'date',
    ];

    protected $appends = [
        'image_url',
        'available_stock',
        'price',
    ];

    public function getImageUrlAttribute(): ?string
    {
        $img = $this->attributes['image'] ?? null;
        if (empty($img)) {
            return null;
        }
        if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
            return $img;
        }
        return asset('storage/' . ltrim($img, '/'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockReceiptItems(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class);
    }

    public function getAvailableStockAttribute(): int
    {
        return max(0, (int) $this->stock_quantity - (int) $this->reserved_quantity);
    }

    public function getIsLowStockAttribute(): bool
    {
        $available = $this->available_stock;
        return $available <= $this->minimum_stock_level && $available > 0;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->available_stock <= 0;
    }

    public function getProfitMarginAttribute(): float
    {
        return (float) (($this->attributes['retail_price'] ?? $this->attributes['price'] ?? 0) - ($this->attributes['wholesale_cost'] ?? 0));
    }

    public function getPriceAttribute(): float
    {
        return (float) ($this->attributes['retail_price'] ?? $this->attributes['price'] ?? 0);
    }

    /**
     * Reserve stock for an active order without decrementing physical stock.
     */
    public function reserveStock(int $quantity): void
    {
        if ($quantity <= 0) return;
        $available = $this->available_stock;
        if ($available < $quantity) {
            throw new \InvalidArgumentException("Insufficient available stock for '{$this->name}'. Available: {$available}, Requested: {$quantity}");
        }
        $this->increment('reserved_quantity', $quantity);
    }

    /**
     * Release previously reserved stock back to available pool.
     */
    public function releaseReservation(int $quantity): void
    {
        if ($quantity <= 0) return;
        $currentReserved = (int) $this->reserved_quantity;
        $toRelease = min($currentReserved, $quantity);
        $this->decrement('reserved_quantity', $toRelease);
    }

    /**
     * Finalize sale upon delivery: deducts physical stock and clears reservation.
     */
    public function finalizeSale(int $quantity): void
    {
        if ($quantity <= 0) return;
        $currentStock = (int) $this->stock_quantity;
        $currentReserved = (int) $this->reserved_quantity;

        $newStock = max(0, $currentStock - $quantity);
        $newReserved = max(0, $currentReserved - $quantity);

        $this->update([
            'stock_quantity' => $newStock,
            'reserved_quantity' => $newReserved,
        ]);
    }

    /**
     * Return delivered stock back into physical inventory.
     */
    public function returnDeliveredStock(int $quantity): void
    {
        if ($quantity <= 0) return;
        $this->increment('stock_quantity', $quantity);
    }
}
