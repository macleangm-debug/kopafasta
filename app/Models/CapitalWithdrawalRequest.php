<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalWithdrawalRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'reviewed_at'  => 'datetime',
        ];
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(FundingPool::class, 'funding_pool_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
