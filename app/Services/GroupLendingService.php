<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\Setting;
use Illuminate\Support\Str;

class GroupLendingService
{
    /** @return array{min: int, max: int} */
    public function memberLimits(): array
    {
        $loan = Setting::group('loan');

        return [
            'min' => max(2, (int) ($loan['group_min_members'] ?? config('group_lending.min_members', 5))),
            'max' => max(2, (int) ($loan['group_max_members'] ?? config('group_lending.max_members', 30))),
        ];
    }

    public function leaderUnlockRepayments(): int
    {
        $loan = Setting::group('loan');

        return max(1, (int) ($loan['group_leader_unlock_repayments'] ?? config('group_lending.leader_unlock_repayments', 2)));
    }

    public function applicationFeePerMember(): bool
    {
        $loan = Setting::group('loan');

        return array_key_exists('group_application_fee_per_member', $loan)
            ? (bool) $loan['group_application_fee_per_member']
            : (bool) config('group_lending.application_fee_per_member', true);
    }

    public function postApprovalFeePerGroup(): bool
    {
        $loan = Setting::group('loan');

        return array_key_exists('group_post_approval_fee_per_group', $loan)
            ? (bool) $loan['group_post_approval_fee_per_group']
            : (bool) config('group_lending.post_approval_fee_per_group', true);
    }

    public function isGroupProduct(?LoanProduct $product): bool
    {
        return $product && (strtoupper($product->code) === 'GL' || ($product->category ?? '') === 'group');
    }

    public function validateMemberCount(int $count): void
    {
        $limits = $this->memberLimits();

        if ($count < $limits['min'] || $count > $limits['max']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'members' => "Group must have between {$limits['min']} and {$limits['max']} members.",
            ]);
        }
    }

    /**
     * @param  list<array{customer_id: int, role?: string, requested_amount?: float}>  $members
     */
    public function createForApplication(
        LoanApplication $application,
        array $members,
        ?string $name = null,
        ?string $purpose = null,
        ?int $targetMemberCount = null,
    ): LoanGroup {
        $application->loadMissing('product', 'customer');

        if (! $this->isGroupProduct($application->product)) {
            throw new \InvalidArgumentException('Not a group loan application.');
        }

        $this->validateMemberCount(count($members));

        $leaderId = collect($members)->firstWhere('role', 'leader')['customer_id']
            ?? $members[0]['customer_id']
            ?? $application->customer_id;

        $group = LoanGroup::create([
            'group_number'           => 'GRP-'.now()->format('ymd').'-'.Str::upper(Str::random(4)),
            'name'                   => $name ?: 'Group '.$application->application_number,
            'purpose'                => $purpose,
            'leader_customer_id'     => $leaderId,
            'primary_application_id' => $application->id,
            'status'                 => 'active',
            'recovery_stage'         => 'individual',
            'target_member_count'    => $targetMemberCount ?: count($members),
        ]);

        foreach (array_values($members) as $index => $row) {
            $isLeader = (int) $row['customer_id'] === (int) $leaderId;
            LoanGroupMember::create([
                'loan_group_id'       => $group->id,
                'customer_id'         => (int) $row['customer_id'],
                'loan_application_id' => (int) $row['customer_id'] === (int) $application->customer_id ? $application->id : null,
                'role'                => $isLeader ? 'leader' : 'member',
                'requested_amount'    => isset($row['requested_amount']) ? (float) $row['requested_amount'] : null,
                'sort_order'          => $index + 1,
                'disbursement_status' => $isLeader ? 'unlocked' : 'locked',
                'disbursement_unlocked_at' => $isLeader ? now() : null,
            ]);
        }

        $application->update(['loan_group_id' => $group->id]);

        return $group->fresh('members');
    }

    public function canDisburseMember(LoanGroupMember $member): bool
    {
        return in_array($member->disbursement_status, ['unlocked', 'disbursed'], true);
    }

    public function recordSuccessfulRepayment(Loan $loan): void
    {
        $loan->loadMissing('application');

        $member = LoanGroupMember::query()
            ->where('loan_id', $loan->id)
            ->when($loan->loan_application_id, fn ($q) => $q->orWhere('loan_application_id', $loan->loan_application_id))
            ->first();

        if (! $member) {
            return;
        }

        $member->increment('successful_repayments');
        $this->maybeUnlockNextMember($member->fresh()->group);
    }

    public function maybeUnlockNextMember(LoanGroup $group): ?LoanGroupMember
    {
        $group->loadMissing('members');

        $leader = $group->members->firstWhere('role', 'leader');
        if (! $leader || $leader->successful_repayments < $this->leaderUnlockRepayments()) {
            return null;
        }

        $next = $group->members
            ->where('disbursement_status', 'locked')
            ->sortBy('sort_order')
            ->first();

        if (! $next) {
            return null;
        }

        $next->update([
            'disbursement_status'      => 'unlocked',
            'disbursement_unlocked_at' => now(),
        ]);

        return $next->fresh();
    }

    public function quotedApplicationFee(?\App\Models\Customer $customer, LoanProduct $product, int $memberCount): int
    {
        $base = quoted_application_fee($customer, $product);

        if (! $this->isGroupProduct($product) || ! $this->applicationFeePerMember()) {
            return $base;
        }

        return (int) round($base * max(1, $memberCount));
    }

    public function advanceRecoveryStage(LoanGroup $group, string $reason = ''): LoanGroup
    {
        $stages = array_keys(config('group_lending.recovery_stages', []));
        $index = array_search($group->recovery_stage, $stages, true);

        if ($index === false || $index >= count($stages) - 1) {
            return $group;
        }

        $group->update([
            'recovery_stage' => $stages[$index + 1],
        ]);

        return $group->fresh();
    }

    public function shouldEscalateToExternal(LoanGroup $group): bool
    {
        return $group->recovery_stage === 'external';
    }

    public function onMemberMissedPayment(LoanGroupMember $member): LoanGroup
    {
        $group = $member->group;

        if ($group->recovery_stage === 'individual') {
            return $this->advanceRecoveryStage($group, 'member_default');
        }

        if ($group->recovery_stage === 'group_liability') {
            return $this->advanceRecoveryStage($group, 'group_liability_exhausted');
        }

        return $group;
    }
}
