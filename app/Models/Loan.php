<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'default_grace_days' => 'integer',
            'penalty_rate_percent' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'disbursement_date' => 'date',
            'maturity_date' => 'date',
            'next_due_date' => 'date',
            'closed_at' => 'datetime',
            'fees_total' => 'decimal:2',
            'net_disbursed_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class);
    }

    public function repaymentSchedules(): HasMany
    {
        return $this->hasMany(RepaymentSchedule::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(Repayment::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(LoanFee::class);
    }
}
