<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lender extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credit_limit'      => 'decimal:2',
            'available_balance' => 'decimal:2',
            'auto_invest'       => 'boolean',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pools(): HasMany
    {
        return $this->hasMany(FundingPool::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(LenderInvestment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LenderTransaction::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(LenderStatement::class);
    }

    public function capitalAllocations(): HasMany
    {
        return $this->hasMany(LoanCapitalAllocation::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(CapitalWithdrawalRequest::class);
    }
}
