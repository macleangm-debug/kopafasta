<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use Illuminate\Support\Facades\DB;

class GroupMemberReplacementService
{
    public function isReplaceable(LoanGroupMember $member): bool
    {
        if ($member->member_status === 'replaced' || $member->isLeader()) {
            return false;
        }

        if ($member->contract_signature_status === 'declined') {
            return true;
        }

        return in_array($member->underwriting_status, ['replacement_requested', 'rejected'], true);
    }

    /** @return list<LoanGroupMember> */
    public function replaceableMembers(LoanApplication $application): array
    {
        $application->loadMissing('loanGroup.members');

        return $application->loanGroup?->members
            ->filter(fn (LoanGroupMember $member) => $this->isReplaceable($member))
            ->values()
            ->all() ?? [];
    }

    /** @return array<string, mixed>|null */
    public function leaderDashboard(LoanApplication $application, Customer $leader): ?array
    {
        if ((int) $application->customer_id !== (int) $leader->id) {
            return null;
        }

        if (! app(GroupContractSignatureService::class)->isGroupApplication($application)) {
            return null;
        }

        $progress = app(GroupContractSignatureService::class)->progress($application);
        if (! $progress) {
            return null;
        }

        $replaceable = collect($this->replaceableMembers($application))->map(fn (LoanGroupMember $member) => [
            'id'                  => $member->id,
            'name'                => $member->customer?->full_name ?? '—',
            'contract_status'     => $member->contract_signature_status ?? 'pending',
            'underwriting_status' => $member->underwriting_status,
            'decline_reason'      => $member->contract_decline_reason,
        ])->values()->all();

        return array_merge($progress, [
            'replaceable' => $replaceable,
            'can_replace' => $replaceable !== [],
        ]);
    }

    public function replaceWithInternalMember(
        LoanApplication $application,
        Customer $leader,
        LoanGroupMember $oldMember,
        Customer $newMember,
    ): LoanGroupMember {
        $this->assertCanReplace($application, $leader, $oldMember);

        if ($application->loanGroup?->members
            ->where('member_status', 'active')
            ->contains(fn (LoanGroupMember $row) => (int) $row->customer_id === (int) $newMember->id)) {
            throw new \InvalidArgumentException(__('borrower.apply.group.replacement_duplicate'));
        }

        return DB::transaction(function () use ($application, $leader, $oldMember, $newMember): LoanGroupMember {
            $this->markReplaced($oldMember);

            $share = app(GroupMemberInvitationService::class)->prepareInternalInvitation(
                $leader,
                $application->product,
                $newMember,
            );

            $invitation = GroupMemberInvitation::findOrFail($share['invitation_id']);
            $invitation->update(['replaces_loan_group_member_id' => $oldMember->id]);

            return $this->createActiveMember($application, $oldMember, $newMember->id, $invitation->id);
        });
    }

    /**
     * @return array{invitation_id: int, share: array<string, mixed>, member: LoanGroupMember|null}
     */
    public function replaceWithExternalInvite(
        LoanApplication $application,
        Customer $leader,
        LoanGroupMember $oldMember,
        string $firstName,
        string $lastName,
        string $phone,
        ?string $email = null,
    ): array {
        $this->assertCanReplace($application, $leader, $oldMember);

        return DB::transaction(function () use ($application, $leader, $oldMember, $firstName, $lastName, $phone, $email): array {
            $this->markReplaced($oldMember);

            $share = app(GroupMemberInvitationService::class)->prepareExternalInvitation(
                $leader,
                $application->product,
                $firstName,
                null,
                $lastName,
                $phone,
                $email,
            );

            $invitation = GroupMemberInvitation::findOrFail($share['invitation_id']);
            $invitation->update(['replaces_loan_group_member_id' => $oldMember->id]);

            return [
                'invitation_id' => $invitation->id,
                'share'         => $share,
                'member'        => null,
            ];
        });
    }

    public function attachReplacementFromInvitation(GroupMemberInvitation $invitation): ?LoanGroupMember
    {
        if (! $invitation->replaces_loan_group_member_id || ! $invitation->customer_id) {
            return null;
        }

        $oldMember = LoanGroupMember::find($invitation->replaces_loan_group_member_id);
        if (! $oldMember || $oldMember->member_status !== 'replaced') {
            return null;
        }

        $application = $oldMember->group?->primaryApplication;
        if (! $application) {
            return null;
        }

        $existing = LoanGroupMember::query()
            ->where('loan_group_id', $oldMember->loan_group_id)
            ->where('customer_id', $invitation->customer_id)
            ->where('member_status', 'active')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createActiveMember(
            $application,
            $oldMember,
            (int) $invitation->customer_id,
            $invitation->id,
        );
    }

    private function assertCanReplace(
        LoanApplication $application,
        Customer $leader,
        LoanGroupMember $member,
    ): void {
        if ((int) $application->customer_id !== (int) $leader->id) {
            throw new \InvalidArgumentException(__('borrower.apply.group.replacement_not_leader'));
        }

        if ((int) $member->group?->primary_application_id !== (int) $application->id) {
            throw new \InvalidArgumentException(__('borrower.apply.group.replacement_not_found'));
        }

        if (! $this->isReplaceable($member)) {
            throw new \InvalidArgumentException(__('borrower.apply.group.replacement_not_allowed'));
        }
    }

    private function markReplaced(LoanGroupMember $member): void
    {
        $member->update([
            'member_status'             => 'replaced',
            'replaced_at'               => now(),
            'disbursement_status'       => 'locked',
        ]);
    }

    private function createActiveMember(
        LoanApplication $application,
        LoanGroupMember $oldMember,
        int $customerId,
        int $invitationId,
    ): LoanGroupMember {
        $group = $oldMember->group;
        $maxSort = (int) $group->members()->max('sort_order');

        return LoanGroupMember::create([
            'loan_group_id'              => $group->id,
            'customer_id'                => $customerId,
            'group_member_invitation_id' => $invitationId,
            'loan_application_id'        => null,
            'role'                       => 'member',
            'member_status'              => 'active',
            'requested_amount'           => $oldMember->requested_amount,
            'sort_order'                 => $maxSort + 1,
            'disbursement_status'        => 'locked',
            'onboarding_status'          => 'complete',
            'underwriting_status'        => 'pending',
            'contract_signature_status'  => 'pending',
        ]);
    }
}
