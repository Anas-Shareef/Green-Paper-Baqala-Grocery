<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'contact_person',
        'tax_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function stockReceipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function purchaseCount(): int
    {
        return $this->stockReceipts()->where('status', 'received')->count();
    }

    public function lastPurchaseDate(): ?string
    {
        return $this->stockReceipts()
            ->where('status', 'received')
            ->latest('receiving_date')
            ->value('receiving_date');
    }
}
