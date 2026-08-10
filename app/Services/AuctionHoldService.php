<?php

namespace App\Services;

use App\Models\ArrearCase;
use App\Models\Loan;
use App\Models\RecoveryAssignment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class AuctionHoldService
{
    public const STATUS_PENDING = 'pending_auction';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_LISTED = 'listed';

    public const STATUS_SOLD = 'sold';

    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly RecoveryPolicyService $policy,
        private readonly RecoveryPartnerService $partners,
        private readonly RecoveryAssignmentService $assignments,
        private readonly LoanCollectionActionService $collectionActions,
        private readonly NotificationService $notifications,
        private readonly ActiveLoanServicingService $servicing,
    ) {}

    /**
     * Debt collector marked repossession complete — start auction hold countdown.
     */
    public function markRepossessed(
        ArrearCase $arrearCase,
        RecoveryAssignment $assignment,
        User $actor,
        ?string $notes = null,
    ): ArrearCase {
        $holdDays = $this->policy->auctionHoldDays();
        $eligibleAt = now()->addDays($holdDays);

        $arrearCase->update([
            'repossessed_at' => now(),
            'auction_eligible_at' => $eligibleAt,
            'auction_status' => self::STATUS_PENDING,
        ]);

        $this->collectionActions->logForCase(
            $arrearCase,
            $actor,
            'repossession_complete',
            'Asset repossessed. Auction hold '.$holdDays.' day(s) — eligible '.$eligibleAt->format('d M Y').'.',
            'repossessed',
            null,
            $assignment,
        );

        $this->notifyBorrower($arrearCase->fresh(['loan.customer']), $holdDays, $eligibleAt);

        return $arrearCase->fresh();
    }

    public function markListed(ArrearCase $arrearCase, User $actor, ?RecoveryAssignment $assignment = null): void
    {
        if (! $arrearCase->repossessed_at) {
            return;
        }

        $arrearCase->update(['auction_status' => self::STATUS_LISTED]);
        $this->collectionActions->logForCase(
            $arrearCase,
            $actor,
            'auction_listed',
            'Asset listed for auction.',
            'listed',
            null,
            $assignment,
        );
    }

    public function markSold(ArrearCase $arrearCase): void
    {
        $arrearCase->update(['auction_status' => self::STATUS_SOLD]);
    }

    public function cancelAuctionHold(ArrearCase $arrearCase, User $actor, string $reason): void
    {
        if (! in_array($arrearCase->auction_status, [self::STATUS_PENDING, self::STATUS_ASSIGNED, self::STATUS_LISTED], true)) {
            return;
        }

        $arrearCase->update(['auction_status' => self::STATUS_CANCELLED]);
        $this->collectionActions->logForCase(
            $arrearCase,
            $actor,
            'auction_hold_cancelled',
            $reason,
            'cancelled',
        );
    }

    /** @return array{repossessed: bool, repossessed_at: ?\Illuminate\Support\Carbon, auction_eligible_at: ?\Illuminate\Support\Carbon, auction_status: ?string, days_until_auction: ?int, hold_days: int, label: ?string}|null */
    public function statusForLoan(Loan $loan): ?array
    {
        $case = ArrearCase::query()
            ->where('loan_id', $loan->id)
            ->whereNotNull('repossessed_at')
            ->where(function ($q) {
                $q->whereNull('auction_status')
                    ->orWhereNotIn('auction_status', [self::STATUS_CANCELLED, self::STATUS_SOLD]);
            })
            ->latest('repossessed_at')
            ->first();

        if (! $case) {
            // Still show sold/cancelled recent state briefly? Prefer any repossessed open case including sold for borrower history
            $case = ArrearCase::query()
                ->where('loan_id', $loan->id)
                ->whereNotNull('repossessed_at')
                ->latest('repossessed_at')
                ->first();
        }

        if (! $case?->repossessed_at) {
            return null;
        }

        $daysUntil = null;
        if ($case->auction_eligible_at && in_array($case->auction_status, [self::STATUS_PENDING, null], true)) {
            $daysUntil = (int) now()->startOfDay()->diffInDays($case->auction_eligible_at->copy()->startOfDay(), false);
        }

        $label = match ($case->auction_status) {
            self::STATUS_SOLD => 'sold',
            self::STATUS_LISTED => 'listed',
            self::STATUS_ASSIGNED => 'auction_assigned',
            self::STATUS_CANCELLED => 'cancelled',
            default => 'pending_auction',
        };

        return [
            'repossessed' => true,
            'repossessed_at' => $case->repossessed_at,
            'auction_eligible_at' => $case->auction_eligible_at,
            'auction_status' => $case->auction_status,
            'days_until_auction' => $daysUntil,
            'hold_days' => $this->policy->auctionHoldDays(),
            'label' => $label,
            'arrear_case_id' => $case->id,
        ];
    }

    /** @return array{assigned: int, skipped: int} */
    public function processEligibleAuctions(bool $dryRun = false): array
    {
        $actor = User::query()
            ->whereIn('role', ['admin', 'super_admin'])
            ->orderBy('id')
            ->first();

        if (! $actor) {
            return ['assigned' => 0, 'skipped' => 0];
        }

        $cases = ArrearCase::query()
            ->with(['loan'])
            ->where('auction_status', self::STATUS_PENDING)
            ->whereNotNull('auction_eligible_at')
            ->where('auction_eligible_at', '<=', now())
            ->orderBy('auction_eligible_at')
            ->get();

        $assigned = 0;
        $skipped = 0;

        foreach ($cases as $case) {
            if ($dryRun) {
                $assigned++;

                continue;
            }

            if ($this->shouldCancelBecauseCleared($case)) {
                $this->cancelAuctionHold($case, $actor, 'Auction hold cancelled — loan outstanding cleared.');
                $skipped++;

                continue;
            }

            if ($this->assignAuctioneer($case, $actor)) {
                $assigned++;
            } else {
                $skipped++;
            }
        }

        return compact('assigned', 'skipped');
    }

    public function assignAuctioneer(ArrearCase $case, User $actor): ?RecoveryAssignment
    {
        $case->loadMissing('loan.product', 'loan.application.collateralAsset');
        $loan = $case->loan;

        if (! $loan || ! $this->policy->partnerTypeAppliesToLoan('auctioneer', $loan)) {
            $this->collectionActions->logForCase(
                $case,
                $actor,
                'escalation',
                'Auction hold ended but auctioneer stage does not apply to this loan — manual review required.',
                'scope_skipped',
            );

            return null;
        }

        $alreadyOpen = RecoveryAssignment::query()
            ->where('arrear_case_id', $case->id)
            ->where('partner_type', 'auctioneer')
            ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
            ->exists();

        if ($alreadyOpen) {
            $case->update(['auction_status' => self::STATUS_ASSIGNED]);

            return null;
        }

        $vendor = $this->preferredAuctioneerAfterRepossession($case);
        if (! $vendor) {
            if (! app(PartnerAutoAssignPolicy::class)->enabledForRecovery('auctioneer')) {
                $this->collectionActions->logForCase(
                    $case,
                    $actor,
                    'escalation',
                    'Auction hold ended but auctioneer auto-assign is disabled — manual assignment required.',
                    'auto_assign_disabled',
                );

                return null;
            }

            $vendor = app(PartnerAutoAssignSelector::class)
                ->pickRecovery('auctioneer', $this->partners->activePartnersForType('auctioneer'));
        }

        if (! $vendor) {
            $this->collectionActions->logForCase(
                $case,
                $actor,
                'escalation',
                'Auction hold ended but no active auctioneer partner is available — manual assignment required.',
                'pending_assignment',
            );

            return null;
        }

        $continuityNote = $vendor->hasPartnerRole('debt_collector')
            ? ' Same collection partner retained for auctioning.'
            : '';

        return DB::transaction(function () use ($case, $vendor, $actor, $continuityNote) {
            $assignment = $this->assignments->assign(
                $case,
                $vendor,
                'auctioneer',
                $actor,
                'Auto-assigned after repossession auction hold ('.$this->policy->auctionHoldDays().' days).'.$continuityNote,
            );

            $case->update(['auction_status' => self::STATUS_ASSIGNED]);

            $this->collectionActions->logForCase(
                $case,
                $actor,
                'recovery_stage_advanced',
                'Auctioneer auto-assigned to '.$vendor->name.' after auction hold.',
                'assigned',
                null,
                $assignment,
            );

            $this->notifyBorrowerAuctionStarted($case->fresh(['loan.customer']));

            return $assignment;
        });
    }

    /**
     * Keep the case with the debt collector who repossessed when they also auction.
     */
    private function preferredAuctioneerAfterRepossession(ArrearCase $case): ?Vendor
    {
        $assignment = RecoveryAssignment::query()
            ->with('vendor')
            ->where('arrear_case_id', $case->id)
            ->where('partner_type', 'debt_collector')
            ->whereIn('status', [
                RecoveryAssignment::STATUS_COMPLETED,
                RecoveryAssignment::STATUS_IN_PROGRESS,
                RecoveryAssignment::STATUS_ASSIGNED,
                RecoveryAssignment::STATUS_ESCALATED,
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        $partner = $assignment?->vendor;
        if (! $partner || $partner->status !== 'active') {
            return null;
        }

        if (! $partner->hasPartnerRole('auctioneer')) {
            return null;
        }

        return $partner;
    }

    private function shouldCancelBecauseCleared(ArrearCase $case): bool
    {
        $loan = $case->loan;
        if (! $loan) {
            return false;
        }

        if (in_array($loan->status, ['closed', 'written_off'], true)) {
            return true;
        }

        $outstanding = (float) ($this->servicing->forLoan($loan)['outstanding_balance'] ?? 0);

        return $outstanding <= 0.01;
    }

    private function notifyBorrower(ArrearCase $case, int $holdDays, $eligibleAt): void
    {
        $customer = $case->loan?->customer;
        $loan = $case->loan;
        if (! $customer || ! $loan) {
            return;
        }

        $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Customer';
        $brand = function_exists('brand_name') ? brand_name() : 'KopaFasta';
        $eligible = $eligibleAt->format('d M Y');

        $this->notifications->notifyCustomer($customer, 'collateral_repossessed', [
            'name' => $name,
            'loan_number' => $loan->loan_number,
            'hold_days' => $holdDays,
            'auction_date' => $eligible,
            '_fallback_subject' => 'Collateral repossessed',
            '_fallback_body' => "Hi {$name}, collateral on loan {$loan->loan_number} has been repossessed. "
                ."You have {$holdDays} day(s) (until {$eligible}) to settle before the asset is auctioned. — {$brand}",
        ]);
    }

    private function notifyBorrowerAuctionStarted(ArrearCase $case): void
    {
        $customer = $case->loan?->customer;
        $loan = $case->loan;
        if (! $customer || ! $loan) {
            return;
        }

        $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Customer';
        $brand = function_exists('brand_name') ? brand_name() : 'KopaFasta';

        $this->notifications->notifyCustomer($customer, 'auction_window_started', [
            'name' => $name,
            'loan_number' => $loan->loan_number,
            '_fallback_subject' => 'Asset moving to auction',
            '_fallback_body' => "Hi {$name}, the auction hold on loan {$loan->loan_number} has ended. "
                ."The repossessed asset is now being prepared for auction. Contact us immediately if you can settle. — {$brand}",
        ]);
    }
}
