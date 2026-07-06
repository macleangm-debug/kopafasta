<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyRedemption extends Model
{
    protected $fillable = [
        'customer_id',
        'option_key',
        'label',
        'benefit_type',
        'benefit_value',
        'fee_type',
        'points_spent',
        'status',
        'expires_at',
        'used_at',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'benefit_value' => 'decimal:4',
            'expires_at'    => 'datetime',
            'used_at'       => 'datetime',
            'metadata'      => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
