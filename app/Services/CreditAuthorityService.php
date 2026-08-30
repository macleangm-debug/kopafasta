<?php

namespace App\Services;

use App\Models\ApprovalLimit;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Settings Hub approval limits are the Source of Truth for who may decide a loan.
 * Grade, Plus membership, and Trust Score inform assessment — they never skip a stage.
 */
class CreditAuthorityService
{
    public const ACTION_LOAN_APPROVE = 'loan_approve';

    /** @var list<string> */
    public const COMMITTEE_ROLES = ['credit_committee'];

    /** @var list<string> */
    public const MANAGEMENT_ROLES = ['manager'];

    public function decisionAmount(LoanApplication $application): float
    {
        return (float) (
            $application->offered_amount
            ?: $application->recommended_amount
            ?: $application->requested_amount
            ?: 0
        );
    }

    /**
     * True when Settings Hub requires Management authorization for a sensitive
     * post-approval action (typically disbursement dual control).
     * Committee remains the credit-approval stage; this does not insert a second underwrite.
     * When no committee/manager loan_approve limits exist, do not invent a fake stage.
     */
    public function managementApprovalRequired(LoanApplication $application, ?User $actor = null): bool
    {
        unset($actor);

        $amount = $this->decisionAmount($application);
        $relevant = $this->activeLimits(self::ACTION_LOAN_APPROVE)
            ->filter(fn (ApprovalLimit $limit) => in_array($limit->role_code, [
                ...self::COMMITTEE_ROLES,
                ...self::MANAGEMENT_ROLES,
            ], true));

        if ($relevant->isEmpty()) {
            return false;
        }

        $committeeCover = $this->coveringLimits($relevant, self::COMMITTEE_ROLES, $amount);
        if ($committeeCover->isEmpty()) {
            return true;
        }

        return $committeeCover->contains(fn (ApprovalLimit $limit) => (bool) $limit->requires_dual_control);
    }

    public function managementRequirementReason(LoanApplication $application): ?string
    {
        if (! $this->managementApprovalRequired($application)) {
            return null;
        }

        $amount = $this->decisionAmount($application);
        $relevant = $this->activeLimits(self::ACTION_LOAN_APPROVE)
            ->filter(fn (ApprovalLimit $limit) => in_array($limit->role_code, [
                ...self::COMMITTEE_ROLES,
                ...self::MANAGEMENT_ROLES,
            ], true));
        $committeeCover = $this->coveringLimits($relevant, self::COMMITTEE_ROLES, $amount);

        if ($committeeCover->isEmpty()) {
            return 'The requested amount is outside the Credit Committee approval band in Settings Hub. Management must authorize disbursement.';
        }

        return 'Settings Hub requires Management authorization for disbursement (dual control). Committee remains the credit decision.';
    }

    public function canManagementApprove(User $user): bool
    {
        $desk = app(CreditDeskAssignmentService::class);

        return $desk->isExempt($user->role) || $desk->onManagementDesk($user);
    }

    /**
     * @return Collection<int, ApprovalLimit>
     */
    public function activeLimits(string $action): Collection
    {
        return ApprovalLimit::query()
            ->where('action', $action)
            ->where('is_active', true)
            ->get();
    }

    /**
     * @param  Collection<int, ApprovalLimit>  $limits
     * @param  list<string>  $roles
     * @return Collection<int, ApprovalLimit>
     */
    public function coveringLimits(Collection $limits, array $roles, float $amount): Collection
    {
        return $limits
            ->filter(fn (ApprovalLimit $limit) => in_array($limit->role_code, $roles, true)
                && (float) $limit->min_amount <= $amount
                && (float) $limit->max_amount >= $amount)
            ->values();
    }
}
