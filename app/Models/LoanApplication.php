<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    public const STAGES = [
        'submitted',
        'screening',
        'credit_appraisal',
        'pre_approval',
        'approval',
        'disbursement',
        'rejected',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'recommended_amount' => 'decimal:2',
            'screening_payload' => 'array',
            'credit_appraisal_payload' => 'array',
            'submitted_at' => 'datetime',
            'pre_approved_at' => 'datetime',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
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

    public function stageHistory(): HasMany
    {
        return $this->hasMany(ApplicationStageHistory::class);
    }

    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ApplicationSignature::class);
    }

    public function customerGuarantors(): HasMany
    {
        return $this->hasMany(CustomerGuarantor::class);
    }
}
