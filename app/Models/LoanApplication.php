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
        'awaiting_management',
        'approval',
        'disbursement',
        'rejected',
    ];

    public const CLOSED_STATUSES = ['rejected', 'withdrawn', 'expired', 'cancelled'];

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
            'rejection_reason_codes' => 'array',
            'submitted_at' => 'datetime',
            'guarantor_deadline_at' => 'datetime',
            'pre_approved_at' => 'datetime',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'offer_issued_at' => 'datetime',
            'offer_responded_at' => 'datetime',
            'recommended_at' => 'datetime',
            'assigned_at' => 'datetime',
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

    public function assignedAnalyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_analyst_id');
    }

    public function hasPendingOffer(): bool
    {
        return $this->offer_status === 'pending_borrower';
    }

    public function isClosed(): bool
    {
        return in_array((string) $this->status, self::CLOSED_STATUSES, true)
            || in_array((string) $this->current_stage, self::CLOSED_STATUSES, true);
    }

    public function closedStatus(): string
    {
        if (in_array((string) $this->status, self::CLOSED_STATUSES, true)) {
            return (string) $this->status;
        }

        if (in_array((string) $this->current_stage, self::CLOSED_STATUSES, true)) {
            return (string) $this->current_stage;
        }

        return (string) $this->status;
    }

    /** Linked loan is past origination — credit management / servicing desk. */
    public function hasActiveFacility(): bool
    {
        $status = (string) ($this->loan?->status ?? '');

        return in_array($status, ['active', 'disbursed', 'arrears', 'defaulted', 'written_off', 'closed'], true);
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

    public function loanGroup(): BelongsTo
    {
        return $this->belongsTo(LoanGroup::class);
    }

    public function disbursementAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerDisbursementAccount::class, 'disbursement_account_id');
    }

    public function collateralAsset(): HasOne
    {
        // Preferred / first submitted collateral (legacy single-asset callers).
        return $this->hasOne(LoanApplicationAsset::class)->oldestOfMany();
    }

    public function collateralAssets(): HasMany
    {
        return $this->hasMany(LoanApplicationAsset::class)->orderByDesc('is_primary')->orderBy('id');
    }

    /**
     * Queue / letterhead party name. Group files: leader first name + remaining members.
     * Example: "Gaspari + 3 others".
     */
    public function partyLabel(): string
    {
        $this->loadMissing(['customer', 'loanGroup.members']);
        $first = trim((string) ($this->customer?->first_name ?? ''));
        if ($first === '') {
            $first = trim((string) ($this->customer?->full_name ?? '—')) ?: '—';
        }

        if (! filled($this->loan_group_id)) {
            return $first;
        }

        $others = max(0, collect($this->loanGroup?->members ?? [])
            ->filter(fn ($member) => ($member->member_status ?? 'active') === 'active')
            ->count() - 1);

        if ($others < 1) {
            return $first;
        }

        return $first.' + '.$others.' '.($others === 1 ? 'other' : 'others');
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
