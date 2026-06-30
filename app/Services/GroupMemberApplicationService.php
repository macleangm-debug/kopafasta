<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanApplicationDraft;
use Illuminate\Support\Collection;

class GroupMemberApplicationService
{
    public function __construct(
        protected GroupMemberProgressService $progress,
        protected RepaymentScheduleGenerator $schedules,
        protected GroupLendingService $groups,
    ) {}

    /** @return array<string, mixed> */
    public function buildViewModel(GroupMemberInvitation $invitation, Customer $member): array
    {
        $invitation->loadMissing(['leader', 'product', 'draft']);

        $profile = app(ProfileCompletionService::class)->calculate($member);
        $profileComplete = app(GroupMemberOnboardingService::class)->memberRequirementsMet($member);
        $draftPayload = $invitation->draft?->payload ?? [];
        $groupPayload = is_array($draftPayload['group'] ?? null) ? $draftPayload['group'] : [];

        $amountPerMember = (float) ($invitation->amount_per_member
            ?? $groupPayload['amount_per_member']
            ?? 0);
        $tenure = (int) ($invitation->requested_tenure_months
            ?? ($draftPayload['form']['requested_tenure_months'] ?? 0)
            ?? ($groupPayload['requested_tenure_months'] ?? 0));
        $product = $invitation->product;
        $cadence = $invitation->repayment_cadence
            ?? ($product ? $this->groups->effectiveRepaymentCadence($product) : 'weekly');
        $rate = (float) ($product?->interest_rate ?? 0);

        $installmentPreview = [];
        if ($amountPerMember > 0 && $tenure > 0 && $product) {
            $installmentPreview = $this->schedules->preview($amountPerMember, $rate, $tenure, $cadence);
        }

        $members = $this->memberRowsForInvitation($invitation, $groupPayload);
        $target = (int) ($groupPayload['target_member_count'] ?? max(1, $members->count()));
        $summary = $this->progress->summarize($members->all(), $target);

        return [
            'invitation'         => $invitation,
            'member'             => $member,
            'profile'            => $profile,
            'profile_complete'   => $profileComplete,
            'profile_url'        => app(ProfileWizardService::class)->resumeUrl($member),
            'draft_reference'    => $invitation->draft_reference ?? $invitation->draft?->draft_reference,
            'group_name'         => $invitation->group_name ?? ($groupPayload['name'] ?? null),
            'group_purpose'      => $this->purposeLabel($invitation->group_purpose ?? ($groupPayload['purpose'] ?? null)),
            'invitation_reason'  => $invitation->invitation_reason,
            'amount_per_member'  => $amountPerMember,
            'tenure_months'      => $tenure,
            'cadence'            => $cadence,
            'cadence_label'      => $cadence === 'weekly'
                ? __('borrower.apply.group_setup.weekly_repayment')
                : __('borrower.apply.group_setup.monthly_repayment'),
            'installment_preview'=> $installmentPreview,
            'members'            => $members->all(),
            'progress'           => $summary,
            'can_finalize'       => app(GroupMemberOnboardingService::class)->canFinalize($member, $invitation),
            'onboarding_url'     => route('site.group-member.onboarding'),
        ];
    }

    /** @return array{show: bool, title: string, message: string, cta_label: string, cta_url: string, reference: string|null}|null */
    public function dashboardBanner(Customer $customer): ?array
    {
        $invitation = app(GroupMemberOnboardingService::class)->pendingInvitationForCustomer($customer);
        if (! $invitation || $invitation->status === 'completed') {
            return null;
        }

        if (app(GroupMemberOnboardingService::class)->isLeaderForInvitation($invitation, $customer)) {
            return null;
        }

        $reference = $invitation->draft_reference ?? $invitation->draft?->draft_reference;
        $leader = $invitation->leader?->full_name ?? brand_name();

        return [
            'show'       => true,
            'title'      => __('borrower.apply.group.dashboard_banner_title', ['reference' => $reference ?: __('borrower.apply.group.loan_label')]),
            'message'    => __('borrower.apply.group.dashboard_banner_message', ['leader' => $leader]),
            'cta_label'  => __('borrower.apply.group.dashboard_banner_cta'),
            'cta_url'    => route('site.group-member.application'),
            'reference'  => $reference,
        ];
    }

    /** @param  array<string, mixed>  $groupPayload */
    protected function memberRowsForInvitation(GroupMemberInvitation $invitation, array $groupPayload): Collection
    {
        $rows = collect($groupPayload['members'] ?? [])
            ->map(fn (array $row) => is_array($row) ? $row : [])
            ->values();

        if ($rows->isEmpty() && $invitation->leader) {
            $rows = collect([[
                'customer_id' => $invitation->leader_customer_id,
                'name'        => $invitation->leader->full_name,
                'role'        => 'leader',
            ]]);
        }

        return $rows->map(function (array $row): array {
            $status = $this->progress->resolveMemberStatus($row);

            return array_merge($row, [
                'status_label' => $status['label'] ?? '',
                'status_key'   => $status['key'] ?? 'pending',
            ]);
        });
    }

    protected function purposeLabel(?string $purpose): ?string
    {
        if (! $purpose) {
            return null;
        }

        $purposes = __('borrower.apply.purposes');

        return is_array($purposes) ? ($purposes[$purpose] ?? $purpose) : $purpose;
    }
}
