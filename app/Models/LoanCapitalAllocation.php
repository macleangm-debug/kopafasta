<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanCapitalAllocation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allocated_principal'           => 'decimal:2',
            'allocation_percent'            => 'decimal:4',
            'partner_interest_share_percent' => 'decimal:2',
            'company_interest_share_percent' => 'decimal:2',
            'interest_earned_partner'       => 'decimal:2',
            'interest_earned_company'       => 'decimal:2',
            'outstanding_exposure'          => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
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
