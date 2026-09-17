<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'whatsapp_number',
        'villa_number',
        'zone',
        'address',
        'whatsapp_marketing_opt_in',
        'status',
        'notes',
    ];

    protected $casts = [
        'whatsapp_marketing_opt_in' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)->whereRaw('is_default = true');
    }

    public function getTotalOrdersAttribute(): int
    {
        return $this->orders()->count();
    }

    public function getTotalSpentAttribute(): float
    {
        return (float) $this->orders()->where('status', '!=', 'cancelled')->sum('total_amount');
    }

    public function getAverageOrderValueAttribute(): float
    {
        $count = $this->orders()->where('status', '!=', 'cancelled')->count();
        if ($count === 0) return 0.0;
        return round($this->total_spent / $count, 2);
    }

    public function getLastOrderDateAttribute()
    {
        return $this->orders()->latest()->first()?->created_at;
    }

    public function getCategoryPreferencesAttribute(): array
    {
        $items = OrderItem::whereHas('order', function ($query) {
            $query->where('customer_id', $this->id)->where('status', '!=', 'cancelled');
        })->with('product.category')->get();

        $categoryCounts = [];
        $totalItems = 0;

        foreach ($items as $item) {
            $catName = $item->product?->category?->name ?? 'General';
            $categoryCounts[$catName] = ($categoryCounts[$catName] ?? 0) + $item->quantity;
            $totalItems += $item->quantity;
        }

        if ($totalItems === 0) return [];

        $percentages = [];
        foreach ($categoryCounts as $name => $count) {
            $percentages[$name] = round(($count / $totalItems) * 100, 1);
        }

        arsort($percentages);
        return $percentages;
    }
}
