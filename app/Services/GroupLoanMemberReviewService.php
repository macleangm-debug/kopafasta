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

        if ($leader && filled($leaderFeedback)) {
            app(NotificationService::class)->notifyInApp(
                $leader,
                __('borrower.apply.group.underwriter_member_feedback', [
                    'name' => $member->customer?->full_name ?? 'Member',
                    'feedback' => $leaderFeedback,
                ]),
                'group_loan',
                'group_member_review',
            );
        }

        return $member->fresh();
    }

    public function updateGroupFeedback(LoanGroup $group, ?string $leaderFeedback, ?User $reviewer = null): LoanGroup
    {
        $group->update(['leader_feedback' => $leaderFeedback]);

        if ($leader = $group->leader) {
            if (filled($leaderFeedback)) {
                app(NotificationService::class)->notifyInApp(
                    $leader,
                    __('borrower.apply.group.underwriter_group_feedback', ['feedback' => $leaderFeedback]),
                    'group_loan',
                    'group_application_review',
                );
            }
        }

        return $group->fresh();
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
}
