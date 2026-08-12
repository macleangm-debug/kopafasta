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
            'pending_invitation'    => __('borrower.apply.group.status.pending_invitation'),
            'invitation_sent'       => __('borrower.apply.group.status.invitation_sent'),
            'link_opened'           => __('borrower.apply.group.status.link_opened'),
            'registration_started'  => __('borrower.apply.group.status.registration_started'),
            'registration_complete' => __('borrower.apply.group.status.registration_complete'),
            'account_registered'    => __('borrower.apply.group.status.account_registered'),
            'profile_complete'      => __('borrower.apply.group.status.profile_complete'),
            'awaiting_signature'    => __('borrower.apply.group.status.awaiting_signature'),
            'kyc_complete'          => __('borrower.apply.group.status.kyc_complete'),
            'profile_incomplete'    => __('borrower.apply.group.status.profile_incomplete'),
            'declined'              => __('borrower.apply.group.status.declined'),
            'expired'               => __('borrower.apply.group.status.expired'),
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
            : $this->wrapStatus('registration_complete');
    }

    public function statusFromInvitation(GroupMemberInvitation $invitation): array
    {
        // Pending always means awaiting Accept/Decline (same as guarantor invites),
        // even when the invitee is already a registered member.
        if ($invitation->status === 'pending') {
            if ($invitation->link_opened_at) {
                return $this->wrapStatus('link_opened');
            }

            return $this->wrapStatus('invitation_sent');
        }

        if ($invitation->customer_id) {
            $customer = Customer::find($invitation->customer_id);

            if ($customer) {
                $base = $this->statusFromCustomer($customer);
                $signed = $invitation->status === 'completed'
                    && app(GroupMemberSignatureService::class)->hasSignature($invitation);

                if (in_array($base['key'], ['kyc_complete', 'profile_complete'], true)) {
                    if ($signed) {
                        return $this->wrapStatus('kyc_complete', true);
                    }

                    return $this->wrapStatus('awaiting_signature');
                }

                if ($base['key'] === 'profile_incomplete') {
                    return $this->wrapStatus('registration_complete');
                }

                return $base;
            }

            return $this->wrapStatus('registration_started');
        }

        if ($invitation->status === 'accepted') {
            return $this->wrapStatus('registration_started');
        }

        if (in_array($invitation->status, ['rejected', 'cancelled'], true)) {
            return $this->wrapStatus('declined');
        }

        if ($invitation->status === 'expired'
            || ($invitation->expires_at && $invitation->expires_at->isPast())) {
            return $this->wrapStatus('expired');
        }

        return $this->wrapStatus('pending_invitation');
    }

    public function statusFromCustomer(Customer $customer): array
    {
        $requirements = app(ApplicationRequirementsService::class)->checklist($customer);

        if ($requirements['can_apply'] ?? false) {
            return $this->wrapStatus('kyc_complete', true);
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
     *     awaiting_acceptance: int,
     *     invitations_pending: int,
     *     pending: int,
     *     members: list<array<string, mixed>>,
     *     can_continue: bool,
     *     can_submit: bool,
     *     summary: list<string>
     * }
     */
    public function summarize(array $members, int $targetCount): array
    {
        $faces = app(FaceVerificationService::class);
        $signatures = app(GroupMemberSignatureService::class);
        $borrowerSignatures = app(BorrowerSignatureService::class);
        $rows = collect($members)->map(function (array $member) use ($faces, $signatures, $borrowerSignatures) {
            $status = $this->resolveMemberStatus($member);
            $profile = $this->profileCompletionForMember($member);
            $customer = $this->resolveCustomer($member);
            $invitationId = (int) ($member['invitation_id'] ?? 0);
            $invitation = $invitationId > 0 ? GroupMemberInvitation::query()->find($invitationId) : null;

            $signatureData = null;
            $signed = false;
            $signedAt = null;
            $signerName = null;

            if ($invitation && $signatures->hasSignature($invitation)) {
                $signed = true;
                $signatureData = $invitation->member_signature_data;
                $signerName = $invitation->member_signer_name;
                $signedAt = optional($invitation->member_signed_at)?->toIso8601String();
            } elseif (($member['role'] ?? '') === 'leader' && $customer) {
                $profileSig = $borrowerSignatures->profileSignature($customer);
                if ($profileSig) {
                    $signatureData = $profileSig['signature_data'];
                    $signerName = $profileSig['signer_name'];
                    $signedAt = $profileSig['signed_at'];
                }
            }

            return array_merge($member, [
                'status_key'       => $status['key'],
                'status_label'     => $status['label'],
                'status_complete'  => $status['complete'],
                'profile_percent'  => $profile['percent'],
                'profile_sections' => $profile['sections'],
                'avatar_url'       => $customer ? $faces->avatarUrl($customer) : ($member['avatar_url'] ?? null),
                'signed'           => $signed,
                'signature_data'   => $signatureData,
                'signer_name'      => $signerName,
                'signed_at'        => $signedAt,
                // Kept for older clients; wizard uses profile_percent; sections belong on loan views.
                'progress_steps'   => [],
            ]);
        })->reject(fn (array $member) => in_array($member['status_key'] ?? '', ['declined', 'expired'], true))
            ->values();

        $added = $rows->count();
        $verified = $rows->where('status_key', 'kyc_complete')->count();
        $profilesComplete = $rows->whereIn('status_key', [
            'profile_complete',
            'awaiting_signature',
            'kyc_complete',
        ])->count();
        $awaitingAcceptance = $rows->whereIn('status_key', [
            'invitation_sent',
            'link_opened',
            'pending_invitation',
        ])->count();
        // Pending invites + members still completing post-accept onboarding (shown in UI cards).
        $invitationsPending = $rows->whereIn('status_key', [
            'invitation_sent',
            'link_opened',
            'pending_invitation',
            'registration_started',
            'registration_complete',
            'account_registered',
            'profile_incomplete',
            'awaiting_signature',
        ])->count();
        $pending = max(0, $targetCount - $added);
        $avgProfilePercent = $added > 0
            ? (int) round($rows->avg(fn (array $row) => (int) ($row['profile_percent'] ?? 0)))
            : 0;

        $canContinue = $targetCount > 0
            && $added === $targetCount
            && $awaitingAcceptance === 0;

        // Submit requires every invited member to confirm membership (profile signature stamp).
        // Leader confirms with their signature on the submit step.
        $nonLeaders = $rows->reject(fn (array $row) => ($row['role'] ?? '') === 'leader');
        $membersSigned = $nonLeaders->every(fn (array $row) => ($row['status_key'] ?? '') === 'kyc_complete');
        $leader = $rows->first(fn (array $row) => ($row['role'] ?? '') === 'leader');
        $leaderReady = ! $leader || in_array($leader['status_key'] ?? '', [
            'kyc_complete',
            'awaiting_signature',
            'profile_complete',
        ], true);
        $canSubmit = $canContinue
            && $profilesComplete === $targetCount
            && $membersSigned
            && $leaderReady;

        $summary = [
            __('borrower.apply.group.progress.added', ['added' => $added, 'target' => $targetCount]),
            __('borrower.apply.group.progress.profiles', ['done' => $profilesComplete, 'target' => $targetCount]),
            __('borrower.apply.group.progress.avg_completion', ['percent' => $avgProfilePercent]),
            __('borrower.apply.group.progress.invitations_pending', ['count' => $invitationsPending]),
        ];

        return [
            'target'                => $targetCount,
            'added'                 => $added,
            'verified'              => $verified,
            'profiles_complete'     => $profilesComplete,
            'avg_profile_percent'   => $avgProfilePercent,
            'awaiting_acceptance'   => $awaitingAcceptance,
            'invitations_pending'   => $invitationsPending,
            'pending'               => $pending,
            'members'               => $rows->all(),
            'can_continue'          => $canContinue,
            'can_submit'            => $canSubmit,
            'summary'               => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $memberRow
     * @return array{percent: int, sections: list<array{key: string, label: string, complete: bool}>}
     */
    public function profileCompletionForMember(array $memberRow): array
    {
        $customer = $this->resolveCustomer($memberRow);
        if (! $customer) {
            return ['percent' => 0, 'sections' => []];
        }

        $calculated = app(ProfileCompletionService::class)->calculate($customer);

        return [
            'percent'  => (int) ($calculated['percent'] ?? 0),
            'sections' => collect($calculated['sections'] ?? [])
                ->map(fn (array $section) => [
                    'key'      => (string) ($section['key'] ?? ''),
                    'label'    => (string) ($section['label'] ?? ''),
                    'complete' => (bool) ($section['complete'] ?? false),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @param  array<string, mixed>  $memberRow */
    private function resolveCustomer(array $memberRow): ?Customer
    {
        $invitationId = (int) ($memberRow['invitation_id'] ?? 0);
        if ($invitationId > 0) {
            $invitation = GroupMemberInvitation::find($invitationId);
            if ($invitation?->customer_id) {
                return Customer::find($invitation->customer_id);
            }
        }
        if (filled($memberRow['customer_id'] ?? null)) {
            return Customer::find((int) $memberRow['customer_id']);
        }

        return null;
    }

    /** @return list<array{key: string, label: string, complete: bool, pending: bool}> */
    public function stepsForMemberRow(array $memberRow): array
    {
        // Legacy checklist removed from the borrower wizard — profile % is the source of truth.
        return [];
    }

    /** @return array{key: string, label: string, complete: bool} */
    private function wrapStatus(string $key, bool $complete = false): array
    {
        $labels = $this->statusLabels();

        return [
            'key'      => $key,
            'label'    => $labels[$key] ?? ucfirst(str_replace('_', ' ', $key)),
            'complete' => $complete || $key === 'kyc_complete',
        ];
    }

    /**
     * Leader view of member progress on a draft group application.
     *
     * @return array<string, mixed>|null
     */
    public function forDraftPayload(?array $groupPayload): ?array
    {
        if (! is_array($groupPayload) || empty($groupPayload['members']) || ! is_array($groupPayload['members'])) {
            return null;
        }

        $members = collect($groupPayload['members'])
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();

        if ($members === []) {
            return null;
        }

        $target = (int) ($groupPayload['target_member_count'] ?? count($members));

        return $this->summarize($members, max(1, $target));
    }

    /**
     * Leader view of member progress on a submitted group application.
     *
     * @return array<string, mixed>|null
     */
    public function forLoanApplication(\App\Models\LoanApplication $application): ?array
    {
        $application->loadMissing(['loanGroup.members.customer', 'loanGroup.members.groupMemberInvitation']);
        $group = $application->loanGroup;
        if (! $group) {
            return null;
        }

        $members = [];
        foreach ($group->members as $member) {
            $inviteName = trim(collect([
                $member->groupMemberInvitation?->invitee_first_name,
                $member->groupMemberInvitation?->invitee_last_name,
            ])->filter()->implode(' '));

            $members[] = [
                'customer_id'      => $member->customer_id,
                'invitation_id'    => $member->group_member_invitation_id,
                'name'             => $member->customer?->full_name ?: ($inviteName !== '' ? $inviteName : 'Member'),
                'phone'            => $member->customer?->phone ?? $member->groupMemberInvitation?->invitee_phone,
                'role'             => $member->role,
                'requested_amount' => $member->requested_amount ?? $member->loan_amount,
            ];
        }

        $target = (int) ($group->target_member_count ?? $group->member_count ?? count($members));

        return $this->summarize($members, max($target, count($members)));
    }
}
