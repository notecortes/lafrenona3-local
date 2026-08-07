<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'restaurant_id',
        'number',
        'status',
        'secret_token',
        'current_session_token',
        'session_token',
        'seated_at',
        'assistance_status',
        'assistance_requested_at',
    ];

    protected $casts = [
        'status' => 'string',
        'assistance_requested_at' => 'datetime',
        'seated_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($table) {
            if (empty($table->secret_token)) {
                $table->secret_token = bin2hex(random_bytes(32));
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isFree(): bool
    {
        return $this->status === 'free';
    }

    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }
}
