<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralTransaction extends Model
{
    protected $fillable = [
        'referral_wallet_id', 'type', 'amount', 'description', 'reference_type', 'reference_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(ReferralWallet::class, 'referral_wallet_id');
    }
}
