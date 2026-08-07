<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'payment_transactions';

    protected $fillable = [
        'restaurant_id',
        'order_id',
        'provider',
        'provider_payment_id',
        'webhook_event_id',
        'idempotency_key',
        'amount_cents',
        'tip_cents',
        'currency',
        'status',
        'confirmed_at',
        'metadata_reference',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'tip_cents' => 'integer',
        'confirmed_at' => 'datetime',
        'metadata_reference' => 'array',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
