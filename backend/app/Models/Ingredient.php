<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'restaurant_id',
        'name',
        'unit',
        'stock_quantity',
        'min_stock',
    ];

    protected $casts = [
        'name' => 'array',
        'stock_quantity' => 'decimal:3',
        'min_stock' => 'decimal:3',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_ingredient')
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock && $this->min_stock > 0;
    }
}
