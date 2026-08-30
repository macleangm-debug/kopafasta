<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanGroup;
use App\Models\LoanGroupMember;
use App\Models\User;

class GroupLoanMemberReviewService
{
    /** @var list<string> */
    public function allowedStatuses(): array
    {
        return ['pending', 'approved', 'rejected', 'flagged', 'replacement_requested'];
    }

    public function reviewMember(
        LoanGroupMember $member,
        string $status,
        ?string $underwritingNotes = null,
        ?string $leaderFeedback = null,
        ?User $reviewer = null,
    ): LoanGroupMember {
        if (! in_array($status, $this->allowedStatuses(), true)) {
            throw new \InvalidArgumentException('Invalid underwriting status.');
        }

        $member->update([
            'underwriting_status' => $status,
            'underwriting_notes'  => $underwritingNotes,
            'leader_feedback'     => $leaderFeedback,
            'reviewed_at'         => now(),
            'reviewed_by_user_id' => $reviewer?->id,
        ]);

        $member->loadMissing('group.primaryApplication', 'customer');
        $application = $member->group?->primaryApplication;
        $leader = $member->group?->leader;

        if ($leader && filled($leaderFeedback) && $application) {
            app(GroupLoanNotificationService::class)->notifyLeaderMemberFeedback(
                $leader,
                $application,
                $member->customer?->full_name ?? 'Member',
                $leaderFeedback,
                $status,
            );
        }

        return $member->fresh();
    }

    public function requestReplacement(
        LoanGroupMember $member,
        ?User $reviewer = null,
        ?string $reason = null,
    ): LoanGroupMember {
        if ($member->isLeader()) {
            throw new \InvalidArgumentException('The group leader cannot be marked for replacement.');
        }

        $name = $member->customer?->full_name ?? 'Member';
        $feedback = filled($reason)
            ? $reason
            : __('borrower.apply.group.admin_replacement_default_feedback', ['name' => $name]);

        return $this->reviewMember(
            $member,
            'replacement_requested',
            null,
            $feedback,
            $reviewer,
        );
    }

    /** @return array<string, mixed>|null */
    public function leaderFeedbackSummary(LoanApplication $application): ?array
    {
        $application->loadMissing(['loanGroup.members.customer', 'loanGroup.leader']);

        $group = $application->loanGroup;
        if (! $group) {
            return null;
        }

        $memberFeedback = $group->members
            ->filter(fn (LoanGroupMember $member) => filled($member->leader_feedback))
            ->map(fn (LoanGroupMember $member) => [
                'name'     => $member->customer?->full_name ?? 'Member',
                'role'     => $member->role,
                'status'   => $member->underwriting_status,
                'feedback' => $member->leader_feedback,
            ])
            ->values()
            ->all();

        if (! filled($group->leader_feedback) && $memberFeedback === []) {
            return null;
        }

        return [
            'group_feedback' => $group->leader_feedback,
            'members'        => $memberFeedback,
        ];
    }

    public function updateGroupFeedback(LoanGroup $group, ?string $leaderFeedback, ?User $reviewer = null): LoanGroup
    {
        $group->update(['leader_feedback' => $leaderFeedback]);

        if ($leader = $group->leader) {
            if (filled($leaderFeedback)) {
                $application = $group->primaryApplication;
                if ($application) {
                    app(GroupLoanNotificationService::class)->notifyLeaderGroupFeedback(
                        $leader,
                        $application,
                        $leaderFeedback,
                    );
                }
            }
        }

        return $group->fresh();
    }

    /**
     * Drop failed members from the active roster when Settings allow continuing
     * with the remaining eligible size. History and fees stay on the file.
     */
    public function continueWithEligibleMembers(
        LoanApplication $application,
        User $reviewer,
        string $reason,
    ): LoanApplication {
        $policy = app(CreditEligibilityPolicyService::class)->evaluate($application->fresh([
            'customer', 'product', 'loanGroup.members.customer',
        ]));
        $resolution = $policy['resolution'] ?? null;
        if (! is_array($resolution) || empty($resolution['allow_continue_without_failed'])) {
            throw new \InvalidArgumentException('This group cannot continue without replacing the failed members.');
        }

        $failedIds = collect($resolution['failed_members'] ?? [])
            ->pluck('customer_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
        $application->loadMissing('loanGroup.members');
        $active = $application->loanGroup?->members
            ?->filter(fn (LoanGroupMember $member) => ($member->member_status ?? 'active') === 'active')
            ?? collect();
        $toDrop = $active->filter(
            fn (LoanGroupMember $member) => ! $member->isLeader() && in_array((int) $member->customer_id, $failedIds, true)
        );
        $remaining = $active->count() - $toDrop->count();
        $min = (int) ($resolution['minimum_eligible_members'] ?? app(GroupLendingService::class)->memberLimits()['min']);
        if ($remaining < $min) {
            throw new \InvalidArgumentException('Remaining members would fall below the minimum group size.');
        }

        foreach ($toDrop as $member) {
            $member->update([
                'member_status' => 'ineligible',
                'underwriting_status' => 'ineligible',
                'leader_feedback' => $reason,
                'replaced_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewer->id,
                'disbursement_status' => 'locked',
            ]);
        }

        return $application->fresh(['loanGroup.members.customer']);
    }
}
