<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanGroupMember;
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

        $amountPerMember = (float) ($groupPayload['amount_per_member']
            ?? $invitation->amount_per_member
            ?? 0);
        $tenure = (int) ($draftPayload['form']['requested_tenure_months']
            ?? $groupPayload['requested_tenure_months']
            ?? $invitation->requested_tenure_months
            ?? 0);
        $product = $invitation->product;
        $cadence = $invitation->repayment_cadence
            ?? ($product ? $this->groups->effectiveRepaymentCadence($product) : 'weekly');
        $rate = (float) ($product?->interest_rate ?? 0);

        $installmentPreview = [];
        $installmentAmount = null;
        if ($amountPerMember > 0 && $tenure > 0 && $product) {
            // Pre-disbursement: amounts only — calendar dates appear after payout.
            $installmentPreview = $this->schedules->previewEstimate($amountPerMember, $rate, $tenure, $cadence);
            $installmentAmount = (float) ($installmentPreview[0]['total_due'] ?? 0);
        }

        $members = $this->memberRowsForInvitation($invitation, $groupPayload);
        $target = (int) ($groupPayload['target_member_count'] ?? max(1, $members->count()));
        $summary = $this->progress->summarize($members->all(), $target);
        $quoteReady = $amountPerMember > 0 && $tenure > 0;
        $installmentLabel = $cadence === 'weekly'
            ? __('borrower.apply.group_setup.weekly_installment_label')
            : __('borrower.apply.group_setup.monthly_installment_label');

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
            'quote_ready'        => $quoteReady,
            'cadence'            => $cadence,
            'cadence_label'      => $cadence === 'weekly'
                ? __('borrower.apply.group_setup.weekly_repayment')
                : __('borrower.apply.group_setup.monthly_repayment'),
            'installment_amount' => $installmentAmount,
            'installment_label'  => $installmentLabel,
            'installment_preview'=> $installmentPreview,
            'members'            => $summary['members'] ?? $members->all(),
            'progress'           => $summary,
            'can_finalize'       => app(GroupMemberOnboardingService::class)->canFinalize($member, $invitation),
            'onboarding_url'     => route('site.group-member.onboarding'),
            'group_payout'       => $this->payoutQueueForInvitation($invitation),
        ];
    }

    /** @return array<string, mixed>|null */
    private function payoutQueueForInvitation(GroupMemberInvitation $invitation): ?array
    {
        $groupMember = \App\Models\LoanGroupMember::query()
            ->where('group_member_invitation_id', $invitation->id)
            ->first();

        if (! $groupMember) {
            return null;
        }

        return app(GroupPayoutService::class)->queueForGroup($groupMember->group);
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

        return loan_purpose_label($purpose)
            ?? display_label($purpose, 'loan_purpose');
    }

    /**
     * Pending invitations plus the live group file after submission.
     *
     * @return list<array<string, mixed>>
     */
    public function applicationRowsForCustomer(Customer $customer): array
    {
        $rows = [];
        $onboarding = app(GroupMemberOnboardingService::class);
        $invitation = $onboarding->pendingInvitationForCustomer($customer);

        if ($invitation
            && $invitation->status !== 'completed'
            && ! $onboarding->isLeaderForInvitation($invitation, $customer)) {
            $rows[] = $this->formatApplicationListRow($invitation->loadMissing(['leader', 'product', 'draft']), $customer);
        }

        $dashboard = app(BorrowerApplicationsDashboardService::class);
        $memberships = LoanGroupMember::query()
            ->with([
                'group.primaryApplication.product',
                'group.primaryApplication.documentRequests',
                'group.primaryApplication.loan',
                'group.primaryApplication.customer',
            ])
            ->where('customer_id', $customer->id)
            ->where('member_status', 'active')
            ->get();

        foreach ($memberships as $member) {
            $application = $member->group?->primaryApplication;
            if (! $application instanceof LoanApplication) {
                continue;
            }
            if ((int) $application->customer_id === (int) $customer->id) {
                continue;
            }
            if (in_array((string) $application->status, ['draft', 'disbursed'], true)) {
                continue;
            }
            if ($application->loan && in_array((string) $application->loan->status, ['active', 'disbursed', 'arrears'], true)) {
                continue;
            }

            $row = $dashboard->formatSubmitted($application);
            if (filled($member->requested_amount)) {
                $row['requested_amount'] = (float) $member->requested_amount;
            }
            $row['is_group_member'] = true;
            $docService = app(ApplicationDocumentRequestService::class);
            $memberActions = $application->documentRequests
                ->filter(fn ($request) => $request->needsBorrowerAction()
                    && $docService->isSubjectOfRequest($customer, $request))
                ->values()
                ->map(fn ($request) => $docService->borrowerGuidedAction($request, $customer))
                ->all();
            if ($memberActions !== []) {
                $row['underwriting_actions'] = $memberActions;
                $row['underwriting_active'] = false;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public function formatApplicationListRow(GroupMemberInvitation $invitation, Customer $customer): array
    {
        $invitation->loadMissing(['leader', 'product', 'draft']);
        $profile = app(ProfileCompletionService::class)->calculate($customer);
        $profileComplete = app(GroupMemberOnboardingService::class)->memberRequirementsMet($customer);
        $reference = $invitation->draft_reference ?? $invitation->draft?->draft_reference;
        $product = $invitation->product;
        $amount = (float) ($invitation->amount_per_member ?? 0);
        $canFinalize = app(GroupMemberOnboardingService::class)->canFinalize($customer, $invitation);

        $applicationPercent = match (true) {
            $invitation->status === 'completed' => 100,
            $canFinalize => 90,
            $profileComplete => 75,
            default => max(10, min(70, (int) ($profile['percent'] ?? 0))),
        };

        $applicationStatus = match (true) {
            $canFinalize => __('borrower.apply.group.sign_and_submit_cta'),
            $profileComplete => __('borrower.apply.group.waiting_for_group'),
            default => __('borrower.apply.group.status.profile_incomplete'),
        };

        return [
            'is_draft'            => false,
            'is_group_invitation' => true,
            'id'                  => 'group-invite-'.$invitation->id,
            'loan_type'           => __('borrower.apply.group.loan_label'),
            'application_number'  => $reference ?: __('borrower.apply.group.loan_label'),
            'product_name'        => $product?->name ?? __('borrower.apply.group.loan_label'),
            'requested_amount'    => $amount > 0 ? $amount : null,
            'requested_tenure_months' => (int) ($invitation->requested_tenure_months ?? 0),
            'status'              => 'group_invitation',
            'status_label'        => __('borrower.apply.group.onboarding_label'),
            'status_tone'         => 'amber',
            'profile_percent'     => (int) ($profile['percent'] ?? 0),
            'profile_complete'    => $profileComplete,
            'application_percent' => $applicationPercent,
            'application_status'  => $applicationStatus,
            'progress_percent'    => $applicationPercent,
            'progress_steps'      => [],
            'created_at'          => $invitation->created_at,
            'updated_at'          => $invitation->updated_at,
            'sort_at'             => ($invitation->updated_at ?? $invitation->created_at)?->timestamp ?? 0,
            'detail'              => __('borrower.apply.group.dashboard_banner_message', [
                'leader' => $invitation->leader?->full_name ?? brand_name(),
            ]),
            'action_url'          => route('site.group-member.application'),
            'action_label'        => __('borrower.apply.group.dashboard_banner_cta'),
            'continue_url'        => $profileComplete && $canFinalize
                ? route('site.group-member.onboarding')
                : route('site.group-member.application'),
            'continue_label'      => $profileComplete
                ? __('borrower.apply.group.sign_and_submit_cta')
                : __('borrower.apply.group.complete_profile_cta'),
        ];
    }
}
