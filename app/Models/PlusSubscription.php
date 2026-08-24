<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlusSubscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'complimentary' => 'boolean',
            'price_paid' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'entitlements' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->expires_at
            && $this->expires_at->isFuture();
    }
}
