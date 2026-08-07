<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_amount',
        'expected_amount',
        'actual_amount',
        'status',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fiscalRecords(): HasMany
    {
        return $this->hasMany(\App\Models\FiscalRecord::class);
    }
}
