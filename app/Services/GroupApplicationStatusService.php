<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanGroup;

class GroupApplicationStatusService
{
    /** @return array<string, string> */
    public function statusLabels(): array
    {
        return collect(config('group_application.statuses', []))
            ->mapWithKeys(fn (array $meta, string $key) => [$key => $meta['label'] ?? ucfirst(str_replace('_', ' ', $key))])
            ->all();
    }

    /**
     * @return array{key: string, label: string, tone: string}
     */
    public function wrap(string $key): array
    {
        $meta = config('group_application.statuses.'.$key, []);

        return [
            'key'   => $key,
            'label' => $meta['label'] ?? ucfirst(str_replace('_', ' ', $key)),
            'tone'  => $meta['tone'] ?? 'gray',
        ];
    }

    /**
     * Resolve status from wizard draft payload (pre-submission).
     *
     * @param  array{name?: string, purpose?: string, target_member_count?: int, members?: list<array<string, mixed>>}  $groupPayload
     * @return array{key: string, label: string, tone: string}
     */
    public function resolveFromDraftPayload(array $groupPayload): array
    {
        $name = trim((string) ($groupPayload['name'] ?? ''));
        $purpose = trim((string) ($groupPayload['purpose'] ?? ''));
        $targetCount = max(0, (int) ($groupPayload['target_member_count'] ?? 0));
        $members = is_array($groupPayload['members'] ?? null) ? $groupPayload['members'] : [];

        if ($name === '' || $purpose === '' || $targetCount < 1) {
            return $this->wrap('draft');
        }

        $progress = app(GroupMemberProgressService::class)->summarize($members, $targetCount);

        if ($progress['added'] < $targetCount) {
            return $this->wrap('inviting_members');
        }

        $invitePipeline = collect($progress['members'])->whereIn('status_key', [
            'pending_invitation',
            'invitation_sent',
            'link_opened',
            'registration_started',
            'registration_complete',
        ])->count();

        if ($invitePipeline > 0) {
            return $this->wrap('inviting_members');
        }

        if ($progress['verified'] < $targetCount) {
            return $this->wrap('member_completion');
        }

        return $this->wrap('ready_for_submission');
    }

    /**
     * @return array{key: string, label: string, tone: string}
     */
    public function resolveFromDraft(LoanApplicationDraft $draft): array
    {
        $group = $draft->payload['group'] ?? [];

        return $this->resolveFromDraftPayload(is_array($group) ? $group : []);
    }

    /**
     * @return array{key: string, label: string, tone: string}
     */
    public function resolveFromApplication(LoanApplication $application): array
    {
        $application->loadMissing(['loan', 'loanGroup']);

        $status = strtolower((string) ($application->status ?? ''));
        $stage = strtolower((string) ($application->current_stage ?? 'submitted'));

        if (in_array($status, ['withdrawn', 'cancelled'], true)) {
            return $this->wrap('cancelled');
        }

        if ($status === 'rejected' || $stage === 'rejected') {
            return $this->wrap('rejected');
        }

        $loan = $application->loan;
        if ($status === 'disbursed'
            || $application->disbursed_at
            || ($loan && in_array((string) $loan->status, ['active', 'disbursed', 'arrears'], true))) {
            return $this->wrap('disbursed');
        }

        if ($status === 'approved'
            || $stage === 'approval'
            || $application->approved_at) {
            return $this->wrap('approved');
        }

        if ($stage === 'disbursement' && ! $application->disbursed_at) {
            return $this->wrap('approved');
        }

        if ($status === 'pending_documents') {
            return $this->wrap('documents_requested');
        }

        return $this->wrap('under_review');
    }

    /**
     * Persist computed status (and optional scoring) on the loan group.
     */
    public function syncApplication(LoanApplication $application, ?array $scoring = null): void
    {
        $application->loadMissing('loanGroup');

        $group = $application->loanGroup;
        if (! $group) {
            return;
        }

        $status = $this->resolveFromApplication($application);
        $updates = ['application_status' => $status['key']];

        if ($scoring !== null) {
            $updates['scoring_snapshot'] = $scoring;
        }

        $group->update($updates);
    }

    /**
     * Build member rows from a persisted loan group for scoring/status.
     *
     * @return list<array<string, mixed>>
     */
    public function memberRowsFromGroup(LoanGroup $group): array
    {
        $group->loadMissing('members.customer', 'members.groupMemberInvitation');

        return $group->members
            ->filter(fn ($member) => ($member->member_status ?? 'active') === 'active')
            ->map(function ($member) {
                $row = [
                    'customer_id'      => $member->customer_id,
                    'invitation_id'    => $member->group_member_invitation_id,
                    'requested_amount' => $member->requested_amount,
                    'role'             => $member->role,
                ];

                if ($member->customer) {
                    $row['name'] = $member->customer->full_name;
                    $row['phone'] = $member->customer->phone;
                }

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string, tone: string}
     */
    public function resolveForGroup(LoanGroup $group, ?LoanApplication $application = null): array
    {
        $application ??= $group->primaryApplication;
        if ($application) {
            return $this->resolveFromApplication($application);
        }

        if (filled($group->application_status)) {
            return $this->wrap((string) $group->application_status);
        }

        return $this->wrap('under_review');
    }
}
