<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;

class GroupLoanReviewService
{
    /** @return array<string, mixed>|null */
    public function dossier(LoanApplication $application): ?array
    {
        $application->loadMissing(['loanGroup.members.customer', 'loanGroup.leader', 'product']);

        $group = $application->loanGroup;
        if (! $group) {
            return null;
        }

        $members = $group->members->map(function ($member) use ($application) {
            $customer = $member->customer;
            $requirements = $customer
                ? app(ApplicationRequirementsService::class)->checklist($customer)
                : ['can_apply' => false, 'items' => []];
            $status = $customer
                ? app(GroupMemberProgressService::class)->statusFromCustomer($customer)
                : ['key' => 'pending_invitation', 'label' => 'Pending', 'complete' => false];

            $latestCrb = $customer
                ? app(CrbCreditCheckService::class)->latest($customer)
                : null;

            return [
                'id'                 => $member->id,
                'role'               => $member->role,
                'name'               => $customer?->full_name ?? '—',
                'customer_number'    => $customer?->customer_number,
                'phone'              => $customer?->phone,
                'national_id'        => $customer?->national_id,
                'requested_amount'   => (float) ($member->requested_amount ?? 0),
                'status_key'         => $status['key'],
                'status_label'       => $status['label'],
                'kyc_complete'       => (bool) ($requirements['can_apply'] ?? false),
                'crb_score'          => $latestCrb['score'] ?? null,
                'crb_status'         => $latestCrb['status'] ?? null,
                'crb_checked_at'     => $latestCrb['checked_at'] ?? null,
                'monthly_income'     => $customer?->income_range,
                'existing_exposure'  => $customer
                    ? (float) $customer->loans()->whereIn('status', ['active', 'disbursed', 'arrears'])->sum('outstanding_balance')
                    : 0,
                'eligible'           => (bool) ($requirements['can_apply'] ?? false),
            ];
        })->values();

        $perMember = (float) ($members->avg('requested_amount') ?: 0);
        $total = (float) $members->sum('requested_amount');

        return [
            'group_number'       => $group->group_number,
            'name'               => $group->name,
            'purpose'            => $group->purpose,
            'leader'             => $group->leader?->full_name,
            'target_member_count'=> (int) ($group->target_member_count ?: $members->count()),
            'member_count'       => $members->count(),
            'amount_per_member'  => $perMember,
            'total_amount'       => $total,
            'members'            => $members->all(),
            'verified_count'     => $members->where('kyc_complete', true)->count(),
        ];
    }
}
