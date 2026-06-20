<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;

class GroupMemberProgressService
{
    /** @return list<string> */
    public function statusLabels(): array
    {
        return [
            'pending_invitation'  => __('borrower.apply.group.status.pending_invitation'),
            'invitation_sent'     => __('borrower.apply.group.status.invitation_sent'),
            'registered'          => __('borrower.apply.group.status.registered'),
            'profile_incomplete'  => __('borrower.apply.group.status.profile_incomplete'),
            'profile_complete'    => __('borrower.apply.group.status.profile_complete'),
            'verification_complete' => __('borrower.apply.group.status.verification_complete'),
        ];
    }

    /**
     * @param  array<string, mixed>  $memberRow
     * @return array{key: string, label: string, complete: bool}
     */
    public function resolveMemberStatus(array $memberRow): array
    {
        $invitationId = (int) ($memberRow['invitation_id'] ?? 0);
        if ($invitationId > 0) {
            $invitation = GroupMemberInvitation::find($invitationId);
            if ($invitation) {
                return $this->statusFromInvitation($invitation);
            }
        }

        $customerId = (int) ($memberRow['customer_id'] ?? 0);
        if ($customerId <= 0) {
            return $this->wrapStatus('pending_invitation');
        }

        $customer = Customer::find($customerId);

        return $customer
            ? $this->statusFromCustomer($customer)
            : $this->wrapStatus('registered');
    }

    public function statusFromInvitation(GroupMemberInvitation $invitation): array
    {
        if ($invitation->customer_id) {
            $customer = Customer::find($invitation->customer_id);

            if ($customer) {
                $base = $this->statusFromCustomer($customer);
                if ($base['complete'] && $invitation->status === 'completed' && app(GroupMemberSignatureService::class)->hasSignature($invitation)) {
                    return $this->wrapStatus('verification_complete', true);
                }

                if ($base['complete'] && $invitation->status !== 'completed') {
                    return $this->wrapStatus('profile_complete');
                }

                return $base;
            }

            return $this->wrapStatus('registered');
        }

        if ($invitation->status === 'pending') {
            return $this->wrapStatus('invitation_sent');
        }

        if ($invitation->status === 'accepted') {
            return $this->wrapStatus('registered');
        }

        return $this->wrapStatus('pending_invitation');
    }

    public function statusFromCustomer(Customer $customer): array
    {
        $requirements = app(ApplicationRequirementsService::class)->checklist($customer);

        if ($requirements['can_apply'] ?? false) {
            return $this->wrapStatus('verification_complete', true);
        }

        $profile = app(ProfileCompletionService::class);
        if ($profile->isFullyComplete($customer)) {
            return $this->wrapStatus('profile_complete');
        }

        return $this->wrapStatus('profile_incomplete');
    }

    /**
     * @param  list<array<string, mixed>>  $members
     * @return array{
     *     target: int,
     *     added: int,
     *     verified: int,
     *     profiles_complete: int,
     *     pending: int,
     *     members: list<array<string, mixed>>,
     *     can_submit: bool,
     *     summary: list<string>
     * }
     */
    public function summarize(array $members, int $targetCount): array
    {
        $rows = collect($members)->map(function (array $member) {
            $status = $this->resolveMemberStatus($member);

            return array_merge($member, [
                'status_key'   => $status['key'],
                'status_label' => $status['label'],
                'status_complete' => $status['complete'],
            ]);
        })->values();

        $added = $rows->count();
        $verified = $rows->where('status_key', 'verification_complete')->count();
        $profilesComplete = $rows->whereIn('status_key', ['profile_complete', 'verification_complete'])->count();
        $pending = max(0, $targetCount - $added);

        $canSubmit = $targetCount > 0
            && $added === $targetCount
            && $verified === $targetCount;

        $summary = [
            __('borrower.apply.group.progress.added', ['added' => $added, 'target' => $targetCount]),
            __('borrower.apply.group.progress.profiles', ['done' => $profilesComplete, 'target' => $targetCount]),
            __('borrower.apply.group.progress.verified', ['done' => $verified, 'target' => $targetCount]),
        ];

        return [
            'target'            => $targetCount,
            'added'             => $added,
            'verified'          => $verified,
            'profiles_complete' => $profilesComplete,
            'pending'           => $pending,
            'members'           => $rows->all(),
            'can_submit'        => $canSubmit,
            'summary'           => $summary,
        ];
    }

    /** @return array{key: string, label: string, complete: bool} */
    private function wrapStatus(string $key, bool $complete = false): array
    {
        $labels = $this->statusLabels();

        return [
            'key'      => $key,
            'label'    => $labels[$key] ?? ucfirst(str_replace('_', ' ', $key)),
            'complete' => $complete || $key === 'verification_complete',
        ];
    }
}
