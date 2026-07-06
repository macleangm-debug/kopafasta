<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralAttribution extends Model
{
    protected $fillable = [
        'referrer_customer_id',
        'session_token',
        'referral_code',
        'expires_at',
        'converted_customer_id',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function isActive(): bool
    {
        return $this->expires_at->isFuture() && $this->converted_customer_id === null;
    }
}
