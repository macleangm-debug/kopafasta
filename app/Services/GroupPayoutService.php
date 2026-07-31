<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\Setting;

class GroupPayoutService
{
    public function payoutOrder(): string
    {
        $loan = Setting::group('loan');
        $order = (string) ($loan['group_payout_order'] ?? config('group_lending.payout_order', 'leader_first'));

        return in_array($order, $this->allowedPayoutOrders(), true) ? $order : 'leader_first';
    }

    /** @return list<string> */
    public function allowedPayoutOrders(): array
    {
        return ['leader_first', 'leader_last', 'manual', 'random', 'rotation', 'committee'];
    }

    public function installmentsBetweenPayouts(): int
    {
        return app(GroupLendingService::class)->leaderUnlockRepayments();
    }

    /**
     * @param  list<array{customer_id: int, role?: string, requested_amount?: float, invitation_id?: int}>  $members
     * @return list<array{customer_id: int, role?: string, requested_amount?: float, invitation_id?: int}>
     */
    public function orderedMembersForCreation(array $members, int $leaderId, ?string $order = null): array
    {
        $order ??= $this->payoutOrder();
        $leader = collect($members)->first(fn (array $row) => (int) ($row['customer_id'] ?? 0) === $leaderId);
        $others = collect($members)
            ->filter(fn (array $row) => (int) ($row['customer_id'] ?? 0) !== $leaderId)
            ->values();

        return match ($order) {
            'leader_last' => $leader ? [...$others->all(), $leader] : array_values($members),
            'manual'      => array_values($members),
            'random'      => $this->shuffleWithLeader($leader, $others),
            'rotation'    => $this->rotationOrder($leader, $others, $leaderId),
            'committee'   => $this->committeeOrder($leader, $others),
            default       => $leader ? [$leader, ...$others->all()] : array_values($members),
        };
    }

    /** @return array<string, mixed> */
    public function queueForGroup(LoanGroup $group): array
    {
        $group->loadMissing(['members.customer', 'members.loan', 'leader', 'primaryApplication']);

        $installmentsRequired = $this->installmentsBetweenPayouts();
        $unlockDays = app(GroupLendingService::class)->unlockDays();
        $members = $group->members->sortBy('sort_order')->values();
        $current = $members->first(fn (LoanGroupMember $member) => in_array($member->disbursement_status, ['unlocked', 'disbursed'], true)
            && $member->disbursement_status !== 'disbursed'
            && ! $members->where('disbursement_status', 'locked')->where('sort_order', '>', $member->sort_order)->isEmpty());

        if (! $current) {
            $current = $members->first(fn (LoanGroupMember $member) => $member->disbursement_status === 'unlocked');
        }

        $next = $members->first(fn (LoanGroupMember $member) => $member->disbursement_status === 'locked');

        $rows = $members->map(function (LoanGroupMember $member) use ($installmentsRequired) {
            $loan = $member->loan;
            $repaymentsMade = (int) ($member->successful_repayments ?? 0);
            $repaymentsRequired = $installmentsRequired;

            return [
                'id'                    => $member->id,
                'sort_order'            => (int) $member->sort_order,
                'role'                  => $member->role,
                'name'                  => $member->customer?->full_name ?? '—',
                'customer_number'       => $member->customer?->customer_number,
                'requested_amount'      => (float) ($member->requested_amount ?? 0),
                'disbursement_status'   => $member->disbursement_status,
                'disbursement_label'    => $this->disbursementStatusLabel((string) $member->disbursement_status),
                'disbursed_at'          => $member->disbursed_at?->toIso8601String(),
                'unlocked_at'           => $member->disbursement_unlocked_at?->toIso8601String(),
                'repayments_made'       => $repaymentsMade,
                'repayments_required'   => $repaymentsRequired,
                'repayments_remaining'  => max(0, $repaymentsRequired - $repaymentsMade),
                'loan_number'           => $loan?->loan_number,
                'outstanding_balance'   => $loan ? (float) $loan->outstanding_balance : null,
                'is_current_recipient'  => in_array($member->disbursement_status, ['unlocked'], true),
            ];
        })->all();

        $groupRepayment = $this->groupRepaymentSummary($group, $members);

        return [
            'group_number'              => $group->group_number,
            'group_name'                => $group->name,
            'leader'                    => $group->leader?->full_name,
            'payout_order'              => $this->payoutOrder(),
            'installments_between'      => $installmentsRequired,
            'unlock_days'               => $unlockDays,
            'current_recipient'         => $current ? [
                'name'   => $current->customer?->full_name,
                'status' => $this->disbursementStatusLabel((string) $current->disbursement_status),
            ] : null,
            'next_recipient'            => $next ? [
                'name'                  => $next->customer?->full_name,
                'repayments_remaining'  => max(0, $installmentsRequired - (int) ($this->gatekeeperMember($group)?->successful_repayments ?? 0)),
            ] : null,
            'members'                   => $rows,
            'group_repayment'           => $groupRepayment,
        ];
    }

    public function gatekeeperMember(LoanGroup $group): ?LoanGroupMember
    {
        $group->loadMissing('members');

        return $group->members
            ->filter(fn (LoanGroupMember $member) => in_array($member->disbursement_status, ['unlocked', 'disbursed'], true))
            ->sortByDesc('sort_order')
            ->first();
    }

    /** @param  \Illuminate\Support\Collection<int, LoanGroupMember>  $members */
    private function groupRepaymentSummary(LoanGroup $group, $members): array
    {
        $totals = [
            'members_disbursed' => $members->where('disbursement_status', 'disbursed')->count(),
            'members_total'     => $members->count(),
            'total_outstanding' => 0.0,
            'total_repaid'      => 0.0,
        ];

        foreach ($members as $member) {
            $loan = $member->loan;
            if (! $loan) {
                continue;
            }

            $totals['total_outstanding'] += (float) $loan->outstanding_balance;
            $totals['total_repaid'] += max(0, (float) $loan->approved_amount - (float) $loan->outstanding_balance);
        }

        return $totals;
    }

    public function disbursementStatusLabel(string $status): string
    {
        return match ($status) {
            'unlocked'  => __('borrower.apply.group.payout.status.unlocked'),
            'disbursed' => __('borrower.apply.group.payout.status.disbursed'),
            'locked'    => __('borrower.apply.group.payout.status.locked'),
            default     => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /** @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $others */
    private function shuffleWithLeader(?array $leader, $others): array
    {
        $shuffled = $others->shuffle()->values()->all();

        return $leader ? [$leader, ...$shuffled] : $shuffled;
    }

    /** @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $others */
    private function rotationOrder(?array $leader, $others, int $leaderId): array
    {
        $sorted = $others->sortBy('customer_id')->values();
        $offset = $leaderId % max(1, $sorted->count() + 1);
        $rotated = $sorted->slice($offset)->concat($sorted->take($offset))->values()->all();

        return $leader ? [$leader, ...$rotated] : $rotated;
    }

    /** @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $others */
    private function committeeOrder(?array $leader, $others): array
    {
        $sorted = $others->sortBy(fn (array $row) => Customer::find((int) ($row['customer_id'] ?? 0))?->full_name ?? '')->values()->all();

        return $leader ? [$leader, ...$sorted] : $sorted;
    }

    public function memberForLoan(Loan $loan): ?LoanGroupMember
    {
        $loan->loadMissing('application.loanGroup');

        if (! $loan->application?->loan_group_id) {
            return null;
        }

        return LoanGroupMember::query()
            ->where('loan_group_id', $loan->application->loan_group_id)
            ->where(function ($query) use ($loan): void {
                $query->where('customer_id', $loan->customer_id)
                    ->orWhere('loan_id', $loan->id);
            })
            ->first();
    }

    public function blockingMessageForLoan(Loan $loan): ?string
    {
        $member = $this->memberForLoan($loan);
        if (! $member) {
            return null;
        }

        if (! app(GroupLendingService::class)->canDisburseMember($member)) {
            return __('admin.group_review.payout.disbursement_locked', [
                'name' => $member->customer?->full_name ?? __('admin.group_review.col_member'),
            ]);
        }

        return null;
    }

    public function markMemberDisbursed(Loan $loan): void
    {
        $member = $this->memberForLoan($loan);
        if (! $member) {
            return;
        }

        $member->update([
            'loan_id'             => $loan->id,
            'disbursement_status' => 'disbursed',
            'disbursed_at'        => now(),
        ]);
    }
}
