<?php

namespace App\Services;

use App\Models\ApplicationSignature;
use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use Illuminate\Validation\ValidationException;

class GroupMemberSignatureService
{
    public function recordForInvitation(
        GroupMemberInvitation $invitation,
        string $signerName,
        string $signatureData,
    ): GroupMemberInvitation {
        $invitation->update([
            'member_signer_name'    => $signerName,
            'member_signature_data' => $signatureData,
            'member_signed_at'      => now(),
        ]);

        return $invitation->fresh();
    }

    /**
     * Stamp the invitation with the member's reusable profile signature (no pad redraw).
     */
    public function confirmFromProfile(
        GroupMemberInvitation $invitation,
        Customer $customer,
    ): GroupMemberInvitation {
        $profile = app(BorrowerSignatureService::class)->profileSignature($customer);
        if (! $profile) {
            throw ValidationException::withMessages([
                'signature_data' => __('borrower.apply.group.profile_signature_required'),
            ]);
        }

        return $this->recordForInvitation(
            $invitation,
            $profile['signer_name'],
            $profile['signature_data'],
        );
    }

    public function attachToApplication(
        LoanApplication $application,
        GroupMemberInvitation $invitation,
    ): ?ApplicationSignature {
        if (! filled($invitation->member_signature_data)) {
            return null;
        }

        return ApplicationSignature::updateOrCreate(
            [
                'loan_application_id'         => $application->id,
                'signer_type'                 => 'group_member',
                'group_member_invitation_id'  => $invitation->id,
            ],
            [
                'signer_name'    => $invitation->member_signer_name ?: $invitation->displayName(),
                'signature_data' => $invitation->member_signature_data,
                'signed_at'      => $invitation->member_signed_at ?? now(),
            ],
        );
    }

    public function hasSignature(GroupMemberInvitation $invitation): bool
    {
        return filled($invitation->member_signature_data);
    }

    /**
     * Membership consent signatures for screening / onboarding carousel.
     *
     * @return array{
     *     signed_count: int,
     *     total: int,
     *     all_signed: bool,
     *     members: list<array<string, mixed>>
     * }
     */
    public function membershipProgressForApplication(LoanApplication $application): array
    {
        $application->loadMissing([
            'loanGroup.members.customer',
            'loanGroup.members.groupMemberInvitation',
            'signatures',
            'customer',
        ]);

        $group = $application->loanGroup;
        $rows = [];

        if ($group) {
            foreach ($group->members->where('member_status', 'active') as $member) {
                $rows[] = $this->membershipRowFromGroupMember($application, $member);
            }
        }

        if ($rows === [] && $application->customer) {
            $borrowerSig = $application->signatures
                ->firstWhere('signer_type', 'borrower');
            $rows[] = [
                'id'             => 0,
                'role'           => 'leader',
                'name'           => $application->customer->full_name,
                'signed'         => filled($borrowerSig?->signature_data),
                'status'         => filled($borrowerSig?->signature_data) ? 'signed' : 'waiting',
                'status_label'   => filled($borrowerSig?->signature_data)
                    ? __('admin.group_review.membership_signature_signed')
                    : __('admin.group_review.membership_signature_waiting'),
                'signer_name'    => $borrowerSig?->signer_name,
                'signature_data' => $borrowerSig?->signature_data,
                'signed_at'      => optional($borrowerSig?->signed_at)?->toIso8601String(),
                'is_you'         => false,
            ];
        }

        $signed = collect($rows)->where('signed', true)->count();
        $total = count($rows);

        return [
            'signed_count' => $signed,
            'total'        => $total,
            'all_signed'   => $total > 0 && $signed === $total,
            'members'      => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $memberRows
     * @return list<array<string, mixed>>
     */
    public function carouselForDraftMembers(array $memberRows, ?Customer $viewer = null): array
    {
        return collect($memberRows)->values()->map(function (array $row, int $index) use ($viewer) {
            $role = (string) ($row['role'] ?? 'member');
            $invitationId = (int) ($row['invitation_id'] ?? 0);
            $customerId = (int) ($row['customer_id'] ?? 0);
            $invitation = $invitationId > 0 ? GroupMemberInvitation::query()->find($invitationId) : null;
            $customer = $customerId > 0 ? Customer::query()->find($customerId) : null;
            if (! $customer && $invitation?->customer_id) {
                $customer = Customer::query()->find($invitation->customer_id);
            }

            $signed = false;
            $signatureData = null;
            $signerName = null;
            $signedAt = null;

            if ($invitation && $this->hasSignature($invitation)) {
                $signed = true;
                $signatureData = $invitation->member_signature_data;
                $signerName = $invitation->member_signer_name;
                $signedAt = optional($invitation->member_signed_at)?->toIso8601String();
            } elseif ($role === 'leader' && $customer) {
                $profile = app(BorrowerSignatureService::class)->profileSignature($customer);
                // Leader confirms on submit — show profile sig as ready, not application-stamped yet.
                if ($profile) {
                    $signatureData = $profile['signature_data'];
                    $signerName = $profile['signer_name'];
                    $signedAt = $profile['signed_at'];
                }
            }

            $isYou = $viewer && $customer && (int) $viewer->id === (int) $customer->id;
            $waiting = ! $signed && $role !== 'leader';

            return [
                'index'          => $index,
                'role'           => $role,
                'name'           => $customer?->full_name
                    ?? ($row['name'] ?? null)
                    ?? ($invitation?->displayName() ?? __('borrower.apply.group.member_badge')),
                'signed'         => $signed,
                'waiting'        => $waiting,
                'leader_pending_submit' => $role === 'leader' && ! $signed,
                'status_label'   => $signed
                    ? __('borrower.apply.group.signature_carousel_signed')
                    : ($role === 'leader'
                        ? __('borrower.apply.group.signature_carousel_leader_pending')
                        : __('borrower.apply.group.signature_carousel_waiting')),
                'signer_name'    => $signerName,
                'signature_data' => $signatureData,
                'signed_at'      => $signedAt,
                'is_you'         => $isYou,
                'profile_ready'  => $customer
                    ? app(BorrowerSignatureService::class)->hasProfileSignature($customer)
                    : false,
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private function membershipRowFromGroupMember(LoanApplication $application, LoanGroupMember $member): array
    {
        $customer = $member->customer;
        $invitation = $member->groupMemberInvitation;
        $appSig = null;

        if ($invitation) {
            $appSig = $application->signatures->first(
                fn ($sig) => $sig->signer_type === 'group_member'
                    && (int) ($sig->group_member_invitation_id ?? 0) === (int) $invitation->id
            );
        }

        if (! $appSig && $member->isLeader()) {
            $appSig = $application->signatures->firstWhere('signer_type', 'borrower');
        }

        $signatureData = $appSig?->signature_data
            ?? $invitation?->member_signature_data;
        $signed = filled($signatureData);

        return [
            'id'             => $member->id,
            'role'           => $member->role,
            'name'           => $customer?->full_name ?? ($invitation?->displayName() ?? '—'),
            'signed'         => $signed,
            'status'         => $signed ? 'signed' : 'waiting',
            'status_label'   => $signed
                ? __('admin.group_review.membership_signature_signed')
                : __('admin.group_review.membership_signature_waiting'),
            'signer_name'    => $appSig?->signer_name
                ?? $invitation?->member_signer_name
                ?? $customer?->full_name,
            'signature_data' => $signatureData,
            'signed_at'      => optional($appSig?->signed_at ?? $invitation?->member_signed_at)?->toIso8601String(),
            'is_you'         => false,
        ];
    }
}
