<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingPool extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_committed' => 'decimal:2',
            'amount_deployed'  => 'decimal:2',
            'expected_yield'   => 'decimal:4',
            'repayment_rate'   => 'decimal:2',
            'default_rate'     => 'decimal:2',
            'min_investment'   => 'decimal:2',
            'start_date'       => 'date',
            'end_date'         => 'date',
            'is_public'        => 'boolean',
        ];
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function investments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LenderInvestment::class);
    }
}
