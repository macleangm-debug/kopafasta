<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:4',
            'bot_regulated_rate' => 'decimal:4',
            'processing_fee_rate' => 'decimal:4',
            'service_fee_rate' => 'decimal:4',
            'administration_fee_rate' => 'decimal:4',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'application_fee_amount' => 'integer',
            'default_grace_days' => 'integer',
            'penalty_rate_percent' => 'decimal:2',
            'requires_collateral' => 'boolean',
            'requires_guarantor' => 'boolean',
            'collateral_rules' => 'array',
            'is_active' => 'boolean',
            'status' => 'string',
        ];
    }

    public function offerLetterTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'offer_letter_template_id');
    }

    public function loanContractTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'loan_contract_template_id');
    }

    public function guarantorAgreementTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'guarantor_agreement_template_id');
    }

    public function assetLendingAgreementTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'asset_lending_agreement_template_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(LoanProductRequirement::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function postApprovalFees(): HasMany
    {
        return $this->hasMany(LoanProductPostApprovalFee::class);
    }

    public function rateTiers(): HasMany
    {
        return $this->hasMany(LoanProductRateTier::class);
    }
}
