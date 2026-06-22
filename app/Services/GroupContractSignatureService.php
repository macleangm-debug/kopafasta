<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;

class GroupContractSignatureService
{
    public function isGroupApplication(LoanApplication $application): bool
    {
        $application->loadMissing('product', 'loanGroup');

        return $application->loanGroup !== null
            && app(GroupLendingService::class)->isGroupProduct($application->product);
    }

    public function memberForCustomer(LoanApplication $application, Customer $customer): ?LoanGroupMember
    {
        $application->loadMissing('loanGroup.members');

        return $application->loanGroup?->members
            ->firstWhere('customer_id', $customer->id);
    }

    /** @return list<array{application: LoanApplication, member: LoanGroupMember}> */
    public function pendingForCustomer(Customer $customer): array
    {
        $rows = LoanGroupMember::query()
            ->with(['group.primaryApplication.product', 'customer'])
            ->where('customer_id', $customer->id)
            ->where('contract_signature_status', 'pending')
            ->whereHas('group.primaryApplication', function ($query): void {
                $query->whereIn('status', ['approved', 'pre_approved', 'disbursed'])
                    ->orWhere('offer_status', 'accepted');
            })
            ->get();

        $pending = [];

        foreach ($rows as $member) {
            $application = $member->group?->primaryApplication;
            if (! $application) {
                continue;
            }

            $readiness = app(ApplicationDisbursementReadinessService::class);
            if (! $readiness->loanContract($application)) {
                continue;
            }

            if ($member->isLeader()) {
                continue;
            }

            $pending[] = [
                'application' => $application,
                'member'      => $member,
            ];
        }

        return $pending;
    }

    /** @return array<string, mixed>|null */
    public function progress(LoanApplication $application): ?array
    {
        if (! $this->isGroupApplication($application)) {
            return null;
        }

        $application->loadMissing('loanGroup.members.customer');
        $members = ($application->loanGroup?->members ?? collect())
            ->filter(fn (LoanGroupMember $member) => ($member->member_status ?? 'active') === 'active');

        $rows = $members->map(function (LoanGroupMember $member) {
            $customer = $member->customer;
            $status = $member->contract_signature_status ?: 'pending';

            return [
                'id'                => $member->id,
                'customer_id'       => $member->customer_id,
                'name'              => $customer?->full_name ?? '—',
                'role'              => $member->role,
                'requested_amount'  => (float) ($member->requested_amount ?? 0),
                'signature_status'  => $status,
                'signature_label'   => $this->statusLabel($status),
                'signed_at'         => $member->contract_signed_at?->toIso8601String(),
                'decline_reason'    => $member->contract_decline_reason,
            ];
        })->values();

        $target = $rows->count();
        $signed = $rows->where('signature_status', 'signed')->count();
        $declined = $rows->where('signature_status', 'declined')->count();
        $pending = $rows->where('signature_status', 'pending')->count();

        return [
            'target'     => $target,
            'signed'     => $signed,
            'declined'   => $declined,
            'pending'    => $pending,
            'all_signed' => $target > 0 && $signed === $target,
            'members'    => $rows->all(),
            'summary'    => [
                __('borrower.apply.group.contract_progress.signed', ['done' => $signed, 'target' => $target]),
                __('borrower.apply.group.contract_progress.pending', ['count' => $pending]),
            ],
        ];
    }

    public function allMembersSigned(LoanApplication $application): bool
    {
        $progress = $this->progress($application);

        return $progress === null || ($progress['all_signed'] ?? false);
    }

    public function hasDeclinedMember(LoanApplication $application): bool
    {
        if (! $this->isGroupApplication($application)) {
            return false;
        }

        return LoanGroupMember::query()
            ->where('loan_group_id', $application->loan_group_id)
            ->where('contract_signature_status', 'declined')
            ->exists();
    }

    public function recordSignature(
        LoanGroupMember $member,
        Customer $customer,
        string $signerName,
        string $signatureData,
    ): LoanGroupMember {
        if ((int) $member->customer_id !== (int) $customer->id) {
            throw new \InvalidArgumentException(__('borrower.apply.group.contract_not_authorized'));
        }

        $member->update([
            'contract_signature_status' => 'signed',
            'contract_signer_name'      => $signerName,
            'contract_signature_data'   => $signatureData,
            'contract_signed_at'        => now(),
            'contract_declined_at'      => null,
            'contract_decline_reason'   => null,
        ]);

        return $member->fresh();
    }

    public function recordDecline(LoanGroupMember $member, Customer $customer, ?string $reason): LoanGroupMember
    {
        if ((int) $member->customer_id !== (int) $customer->id) {
            throw new \InvalidArgumentException(__('borrower.apply.group.contract_not_authorized'));
        }

        $member->update([
            'contract_signature_status' => 'declined',
            'contract_declined_at'      => now(),
            'contract_decline_reason'   => $reason,
        ]);

        return $member->fresh();
    }

    public function syncLeaderFromContract(LoanApplication $application): void
    {
        if (! $this->isGroupApplication($application)) {
            return;
        }

        $readiness = app(ApplicationDisbursementReadinessService::class);
        if (! $readiness->loanContract($application)?->isSigned()) {
            return;
        }

        $application->loadMissing('loanGroup.members', 'signatures');
        $leaderMember = $application->loanGroup?->members->firstWhere('role', 'leader');
        if (! $leaderMember) {
            return;
        }

        $borrowerSignature = $application->signatures->firstWhere('signer_type', 'borrower');
        $contract = $readiness->loanContract($application);

        $leaderMember->update([
            'contract_signature_status' => 'signed',
            'contract_signer_name'      => $borrowerSignature?->signer_name
                ?? $application->customer?->full_name,
            'contract_signature_data'   => $contract?->acceptance_signature_data
                ?? $borrowerSignature?->signature_data,
            'contract_signed_at'        => $contract?->signed_at ?? now(),
        ]);
    }

    public function notifyPendingMembers(LoanApplication $application): void
    {
        if (! $this->isGroupApplication($application)) {
            return;
        }

        $readiness = app(ApplicationDisbursementReadinessService::class);
        if (! $readiness->loanContract($application)) {
            return;
        }

        $application->loadMissing('loanGroup.members.customer', 'customer');
        $leaderId = (int) ($application->customer_id ?? 0);

        foreach ($application->loanGroup?->members ?? [] as $member) {
            if (($member->member_status ?? 'active') !== 'active') {
                continue;
            }

            if ((int) $member->customer_id === $leaderId) {
                continue;
            }

            if (($member->contract_signature_status ?? 'pending') !== 'pending') {
                continue;
            }

            $customer = $member->customer;
            if (! $customer) {
                continue;
            }

            app(GroupLoanNotificationService::class)->notifyMemberContractSignRequired(
                $customer,
                $application,
            );
        }
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'signed'   => __('borrower.apply.group.contract_status.signed'),
            'declined' => __('borrower.apply.group.contract_status.declined'),
            default    => __('borrower.apply.group.contract_status.pending'),
        };
    }
}
