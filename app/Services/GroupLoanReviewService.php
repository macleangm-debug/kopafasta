<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanGroupMember;

class GroupLoanReviewService
{
    /** @return array<string, mixed>|null */
    public function dossier(LoanApplication $application): ?array
    {
        $application->loadMissing(['loanGroup.members.customer', 'loanGroup.members.groupMemberInvitation', 'loanGroup.leader', 'product']);

        $group = $application->loanGroup;
        if (! $group) {
            return null;
        }

        $members = $group->members
            ->filter(fn (LoanGroupMember $member) => ($member->member_status ?? 'active') === 'active')
            ->map(function (LoanGroupMember $member) use ($application) {
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

                $memberCrb = collect($application->credit_appraisal_payload['group_member_crb'] ?? [])
                    ->firstWhere('customer_id', $customer?->id);

                $crbSummary = $customer
                    ? app(CrbCreditCheckService::class)->summaryForCustomer($customer)
                    : [];
                if (is_array($memberCrb) && filled($memberCrb['score'] ?? null) && ! filled($crbSummary['score'] ?? null)) {
                    $crbSummary['score'] = $memberCrb['score'];
                }
                $crbExplain = app(CrbCreditCheckService::class)->recommendationExplanation($crbSummary);

                $file = $customer
                    ? app(LoanApplicationReviewService::class)->subjectFileForCustomer($customer)
                    : null;

                return [
                    'id' => $member->id,
                    'role' => $member->role,
                    'name' => $customer?->full_name ?? '—',
                    'customer_id' => $customer?->id,
                    'customer_number' => $customer?->customer_number,
                    'phone' => $customer?->phone,
                    'national_id' => $customer?->national_id,
                    'requested_amount' => (float) ($member->requested_amount ?? 0),
                    'status_key' => $status['key'],
                    'status_label' => $status['label'],
                    'kyc_complete' => (bool) ($requirements['can_apply'] ?? false),
                    'profile_complete' => (bool) ($requirements['can_apply'] ?? false),
                    'crb_score' => $memberCrb['score'] ?? $crbSummary['score'] ?? $latestCrb?->score,
                    'crb_status' => $memberCrb['error'] ?? ($latestCrb || ($crbSummary['score'] ?? null) !== null ? 'checked' : 'Not checked'),
                    'crb_recommendation' => strtolower((string) ($crbSummary['recommendation'] ?? '')),
                    'crb_existing_loans' => (int) ($crbSummary['existing_loans'] ?? 0),
                    'crb_outstanding' => (float) ($crbSummary['outstanding_balance'] ?? 0),
                    'crb_delinquencies' => (int) ($crbSummary['delinquencies'] ?? 0),
                    'crb_summary' => (string) ($crbExplain['summary'] ?? ''),
                    'crb_checked_at' => $memberCrb['checked_at'] ?? $latestCrb?->checked_at?->toIso8601String(),
                    'monthly_income' => $customer?->income_range,
                    'existing_exposure' => $customer
                        ? (float) $customer->loans()->whereIn('status', ['active', 'disbursed', 'arrears'])->sum('outstanding_balance')
                        : 0,
                    'eligible' => (bool) ($requirements['can_apply'] ?? false),
                    'underwriting_status' => $member->underwriting_status ?? 'pending',
                    'underwriting_notes' => $member->underwriting_notes,
                    'leader_feedback' => $member->leader_feedback,
                    'contract_signature_status' => $member->contract_signature_status ?? 'pending',
                    'can_request_replacement' => ! $member->isLeader()
                        && ($member->member_status ?? 'active') === 'active'
                        && ($member->underwriting_status ?? 'pending') !== 'replacement_requested',
                    'file' => $file,
                ];
            })->values();

        $perMember = (float) ($members->avg('requested_amount') ?: 0);
        $total = (float) $members->sum('requested_amount');
        $groupAfford = app(GroupAffordabilityService::class)->evaluate($application);
        $affordByCustomer = collect($groupAfford['members'] ?? [])->keyBy('customer_id');
        $members = $members->map(function (array $row) use ($affordByCustomer) {
            $row['affordability'] = $affordByCustomer->get($row['customer_id'] ?? 0);

            return $row;
        });

        return [
            'group_number' => $group->group_number,
            'name' => $group->name,
            'purpose' => $group->purpose,
            'leader' => $group->leader?->full_name,
            'leader_feedback' => $group->leader_feedback,
            'target_member_count' => (int) ($group->target_member_count ?: $members->count()),
            'member_count' => $members->count(),
            'amount_per_member' => $perMember,
            'total_amount' => $total,
            'members' => $members->all(),
            'verified_count' => $members->where('kyc_complete', true)->count(),
            'contract_signatures' => app(GroupContractSignatureService::class)->progress($application),
            'membership_signatures' => app(GroupMemberSignatureService::class)->membershipProgressForApplication($application),
            'payout_queue' => app(GroupPayoutService::class)->queueForGroup($group),
            'statuses' => app(GroupLoanMemberReviewService::class)->allowedStatuses(),
            'application_status' => app(GroupApplicationStatusService::class)->resolveForGroup($group, $application),
            'scoring' => $group->scoring_snapshot
                ?: app(GroupScoringService::class)->scoreForGroup($group, $application),
            'affordability' => $groupAfford,
        ];
    }
}
