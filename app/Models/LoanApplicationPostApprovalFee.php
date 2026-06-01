<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationPostApprovalFee extends Model
{
    protected $fillable = [
        'loan_application_id', 'loan_product_post_approval_fee_id',
        'code', 'name', 'fee_type', 'configured_amount', 'calculated_amount',
        'amount_paid', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'configured_amount' => 'decimal:4',
            'calculated_amount' => 'decimal:2',
            'amount_paid'       => 'decimal:2',
            'paid_at'           => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
