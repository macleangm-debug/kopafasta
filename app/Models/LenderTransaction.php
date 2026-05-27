<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LenderTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'processed_at' => 'datetime',
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

    public function investment(): BelongsTo
    {
        return $this->belongsTo(LenderInvestment::class, 'lender_investment_id');
    }
}
