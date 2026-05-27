<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LenderInvestment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'return_amount' => 'decimal:2',
            'return_rate' => 'decimal:4',
            'invested_at' => 'date',
            'matures_at' => 'date',
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

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
