<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** Borrower-facing post-approval pipeline stages (stored in current_stage). */
    public const BORROWER_STAGE_POST_APPROVAL_FEES = 'post_approval_fees';

    public const BORROWER_STAGE_AWAITING_DISBURSEMENT_DETAILS = 'awaiting_disbursement_details';

    public const BORROWER_STAGE_CONTRACT = 'contract_generation';

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'recommended_amount' => 'decimal:2',
            'offered_amount' => 'decimal:2',
            'screening_payload' => 'array',
            'credit_appraisal_payload' => 'array',
            'submitted_at' => 'datetime',
            'pre_approved_at' => 'datetime',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'offer_issued_at' => 'datetime',
            'offer_responded_at' => 'datetime',
            'recommended_at' => 'datetime',
            'disbursement_details_confirmed_at' => 'datetime',
            'disbursement_details_snapshot' => 'array',
            'borrower_completed_steps' => 'array',
        ];
    }

    public function alternativeProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'alternative_loan_product_id');
    }

    public function recommendedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by');
    }

    public function hasPendingOffer(): bool
    {
        return $this->offer_status === 'pending_borrower';
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

    public function documentRequests(): HasMany
    {
        return $this->hasMany(LoanApplicationDocumentRequest::class);
    }

    public function postApprovalFees(): HasMany
    {
        return $this->hasMany(LoanApplicationPostApprovalFee::class);
    }

    public function assetReservation(): HasOne
    {
        return $this->hasOne(AssetReservation::class);
    }

    public function disbursementAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerDisbursementAccount::class, 'disbursement_account_id');
    }

    public function collateralAsset(): HasOne
    {
        return $this->hasOne(LoanApplicationAsset::class);
    }

    public function valuationAssignments(): HasMany
    {
        return $this->hasMany(ValuationAssignment::class);
    }

    public function manualPostApprovalFees(): HasMany
    {
        return $this->hasMany(ManualPostApprovalFee::class);
    }
}
