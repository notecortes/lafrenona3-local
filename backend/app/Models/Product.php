<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'description',
        'price',
        'weekend_price',
        'image_url',
        'stock_status',
        'is_active',
        'is_available',
        'is_vegan',
        'is_vegetarian',
        'allergens',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'allergens' => 'array',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'is_vegan' => 'boolean',
        'is_vegetarian' => 'boolean',
        'price' => 'decimal:2',
        'weekend_price' => 'decimal:2',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function ingredients(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredient')
            ->withPivot('quantity_required')
            ->withTimestamps();
    }
}
