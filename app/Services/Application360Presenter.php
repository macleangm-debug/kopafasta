<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Thin Application 360 composer for admin loan-application show.
 * Delegates to ScreeningNextActionService / GuidedApprovalService / PostApprovalNextActionService
 * and existing application records — does not invent a second workflow engine.
 */
class Application360Presenter
{
    public function __construct(
        private readonly ScreeningNextActionService $screeningNext,
        private readonly GuidedApprovalService $guidedApproval,
        private readonly PostApprovalNextActionService $postApprovalNext,
        private readonly LoanApplicationWorkflowService $workflow,
        private readonly ApplicationDocumentRequestService $documents,
        private readonly CollateralSecureService $collateralSecure,
        private readonly ApplicationOfferService $offers,
    ) {}

    /**
     * @param  Collection<int, mixed>|null  $stageHistory
     * @param  Collection<int, LoanApplicationDocumentRequest>|null  $documentRequests
     * @return array<string, mixed>
     */
    public function forApplication(
        LoanApplication $application,
        ?User $actor = null,
        ?Collection $stageHistory = null,
        ?Collection $documentRequests = null,
    ): array {
        $actor = $actor ?? auth()->user();
        $application->loadMissing([
            'customer', 'product', 'loan', 'loanGroup.members.customer', 'loanGroup.leader',
            'customerGuarantors.invitation', 'collateralAssets', 'assignedAnalyst',
        ]);

        $stage = (string) ($application->current_stage ?? 'submitted');
        $customer = $application->customer;
        $product = $application->product;
        $amount = (float) ($application->recommended_amount
            ?? $application->requested_amount
            ?? 0);

        $next = $this->canonicalNext($application, $actor, $stage);
        $lifecycle = $this->lifecycle($application, $stage);
        $attention = $this->attentionSummaries($application, $documentRequests, $next);

        return [
            'application_number' => $application->application_number,
            'member_name' => $application->partyLabel(),
            'member_no' => $customer?->member_no,
            'member_url' => $customer
                ? route('admin.customers.show', $customer)
                : null,
            'product_name' => $product?->name,
            'amount' => $amount,
            'amount_label' => $amount > 0 ? format_money($amount) : '—',
            'stage' => $stage,
            'stage_label' => $this->workflow->stageLabel($stage),
            'status' => (string) $application->status,
            'status_label' => display_label($application->status, 'application_status'),
            'gate_label' => $next['gate_label'] ?? null,
            'progress_percent' => $next['percent'] ?? null,
            'overall_status' => $this->overallStatus($next, $application),
            'next' => $next,
            'lifecycle' => $lifecycle,
            'attention' => $attention,
            'timeline' => $this->timeline($application, $stageHistory),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalNext(LoanApplication $application, ?User $actor, string $stage): array
    {
        $screeningStages = ['submitted', 'screening', 'credit_appraisal', 'under_review'];
        $committeeStages = ['pre_approval'];
        $postStages = [
            'awaiting_management', 'approval', 'post_approval_fees',
            'awaiting_disbursement_details', 'contract_generation', 'disbursement',
        ];

        if (in_array($stage, $screeningStages, true)
            || in_array((string) $application->status, ['pending_documents', 'under_review'], true)) {
            $row = $this->screeningNext->forApplication($application, $actor);
            $ctaKind = (string) ($row['cta_kind'] ?? 'continue');
            $href = match ($ctaKind) {
                'waiting' => (string) ($row['desk_href'] ?? $row['href'] ?? '#'),
                'decision' => (string) ($row['href'] ?? $row['desk_href'] ?? '#'),
                default => (string) ($row['review_href'] ?? $row['href'] ?? '#'),
            };

            return [
                'source' => 'screening',
                'headline' => (string) ($row['what_happens_next'] ?? $row['cta'] ?? 'Continue Screening'),
                'cta' => (string) ($row['cta'] ?? 'Continue Screening'),
                'href' => $href,
                'cta_kind' => $ctaKind,
                'gate_label' => (string) ($row['current_gate_label'] ?? $row['gate_label'] ?? ''),
                'percent' => $row['percent'] ?? null,
                'bucket' => $row['bucket'] ?? null,
            ];
        }

        if (in_array($stage, $committeeStages, true)) {
            $row = $this->guidedApproval->committeeNext($application);

            return [
                'source' => 'committee',
                'headline' => (string) ($row['what_happens_next'] ?? $row['cta'] ?? 'Committee review'),
                'cta' => (string) ($row['cta'] ?? 'Continue Committee Review'),
                'href' => (string) ($row['review_href'] ?? $row['href'] ?? '#'),
                'cta_kind' => ($row['bucket'] ?? '') === 'waiting' ? 'waiting' : 'continue',
                'gate_label' => null,
                'percent' => null,
                'bucket' => $row['bucket'] ?? null,
            ];
        }

        if (in_array($stage, $postStages, true) || $application->hasActiveFacility()) {
            if ($application->hasActiveFacility()) {
                return [
                    'source' => 'servicing',
                    'headline' => 'Facility is live — manage from Credit management',
                    'cta' => 'View credit management',
                    'href' => route('admin.loan-applications.show', $application),
                    'cta_kind' => 'completed',
                    'gate_label' => null,
                    'percent' => 100,
                    'bucket' => 'completed',
                ];
            }

            $row = $this->postApprovalNext->forApplication($application, $actor);
            $ctaKind = (string) ($row['cta_kind'] ?? 'continue');

            return [
                'source' => 'post_approval',
                'headline' => (string) ($row['next_action'] ?? $row['cta'] ?? 'Continue Post-Approval'),
                'cta' => (string) ($row['cta'] ?? 'Continue Post-Approval'),
                'href' => (string) (
                    in_array($ctaKind, ['continue', 'start'], true)
                        ? ($row['review_href'] ?? $row['href'] ?? '#')
                        : ($row['href'] ?? $row['desk_href'] ?? '#')
                ),
                'cta_kind' => $ctaKind,
                'gate_label' => null,
                'percent' => $row['percent'] ?? null,
                'bucket' => $row['bucket'] ?? null,
            ];
        }

        if ($application->isClosed()) {
            return [
                'source' => 'closed',
                'headline' => $application->closedReasonLabel() ?: 'File closed',
                'cta' => 'View file',
                'href' => route('admin.loan-applications.show', $application),
                'cta_kind' => 'completed',
                'gate_label' => null,
                'percent' => null,
                'bucket' => 'completed',
            ];
        }

        return [
            'source' => 'file',
            'headline' => 'Open the credit file',
            'cta' => 'View Overview',
            'href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ]),
            'cta_kind' => 'continue',
            'gate_label' => null,
            'percent' => null,
            'bucket' => null,
        ];
    }

    /**
     * @return list<array{key: string, label: string, state: string, href: ?string}>
     */
    private function lifecycle(LoanApplication $application, string $stage): array
    {
        $order = [
            'application' => 'Application',
            'screening' => 'Screening',
            'decision' => 'Decision',
            'committee' => 'Committee',
            'offer' => 'Offer',
            'post_approval' => 'Post-Approval',
            'contract' => 'Contract',
            'disbursement' => 'Disbursement',
        ];

        $currentKey = match (true) {
            $application->hasActiveFacility() || $stage === 'disbursement' || $application->status === 'disbursed'
                => 'disbursement',
            in_array($stage, ['contract_generation'], true) => 'contract',
            in_array($stage, ['approval', 'post_approval_fees', 'awaiting_disbursement_details', 'awaiting_management'], true)
                => 'post_approval',
            in_array((string) $application->status, ['awaiting_offer'], true)
                || in_array((string) ($application->offer_status ?? ''), ['pending_borrower', 'accepted', 'declined'], true)
                => 'offer',
            $stage === 'pre_approval' => 'committee',
            in_array($stage, ['credit_appraisal'], true) => 'decision',
            in_array($stage, ['submitted', 'screening', 'under_review'], true)
                || in_array((string) $application->status, ['pending_documents', 'under_review'], true)
                => 'screening',
            default => 'application',
        };

        $keys = array_keys($order);
        $currentIndex = array_search($currentKey, $keys, true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $attentionKeys = $this->lifecycleAttentionKeys($application, $stage);
        $rows = [];
        foreach ($order as $key => $label) {
            $index = array_search($key, $keys, true);
            $state = match (true) {
                $application->isClosed() && $index <= $currentIndex => 'complete',
                $index < $currentIndex => 'complete',
                $index === $currentIndex && in_array($key, $attentionKeys, true) => 'attention',
                $index === $currentIndex => 'current',
                default => 'upcoming',
            };
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'state' => $state,
                'href' => $this->lifecycleHref($application, $key),
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function lifecycleAttentionKeys(LoanApplication $application, string $stage): array
    {
        $keys = [];
        if (in_array((string) $application->status, ['pending_documents'], true)) {
            $keys[] = 'screening';
        }
        if (in_array((string) $application->status, ['awaiting_offer'], true)
            || (string) ($application->offer_status ?? '') === 'pending_borrower') {
            $keys[] = 'offer';
        }
        if (in_array($stage, ['post_approval_fees', 'awaiting_disbursement_details'], true)) {
            $keys[] = 'post_approval';
        }

        return array_values(array_unique($keys));
    }

    private function lifecycleHref(LoanApplication $application, string $key): ?string
    {
        return match ($key) {
            'screening' => route('admin.loan-applications.guided-screening', $application),
            'decision', 'committee' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => $key === 'decision' ? 'decision' : 'overview',
            ]),
            'offer', 'post_approval', 'contract', 'disbursement' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ]),
            default => route('admin.loan-applications.show', $application),
        };
    }

    /**
     * @param  Collection<int, LoanApplicationDocumentRequest>|null  $documentRequests
     * @param  array<string, mixed>  $next
     * @return list<array{label: string, detail: ?string, href: ?string, tone: string}>
     */
    private function attentionSummaries(
        LoanApplication $application,
        ?Collection $documentRequests,
        array $next,
    ): array {
        $rows = [];

        $feeStatus = (string) ($application->application_fee_status ?? '');
        if ($feeStatus !== '' && ! in_array($feeStatus, ['paid', 'waived', 'charged'], true)) {
            $rows[] = [
                'label' => 'Application fee outstanding',
                'detail' => display_label($feeStatus, 'payment_status') ?: $feeStatus,
                'href' => null,
                'tone' => 'attention',
            ];
        } elseif (in_array($feeStatus, ['paid', 'waived', 'charged'], true) || $feeStatus === '') {
            // Quiet when paid or not tracked on submitted file
        }

        $openDocs = ($documentRequests ?? collect())
            ->filter(fn ($req) => ! in_array((string) ($req->status ?? ''), ['satisfied', 'cancelled', 'closed'], true));
        if ($openDocs->isNotEmpty()) {
            $first = $openDocs->sortBy(fn ($r) => $r->deadline_at?->timestamp ?? PHP_INT_MAX)->first();
            $due = $first?->deadline_at ? 'due '.$first->deadline_at->format('d M Y') : null;
            $rows[] = [
                'label' => $openDocs->count() === 1
                    ? 'Requested document outstanding'
                    : $openDocs->count().' document requests outstanding',
                'detail' => $due,
                'href' => route('admin.loan-applications.show', [
                    'loan_application' => $application,
                    'workspace' => 'checklist',
                ]),
                'tone' => 'attention',
            ];
        } else {
            $rows[] = [
                'label' => 'Documents',
                'detail' => 'Complete',
                'href' => null,
                'tone' => 'quiet',
            ];
        }

        try {
            $collateral = $this->collateralSecure->state($application);
            if (is_array($collateral) && $this->collateralSecure->isOpen($application)) {
                $rows[] = [
                    'label' => 'Collateral needs attention',
                    'detail' => (string) ($collateral['status'] ?? 'open'),
                    'href' => route('admin.loan-applications.show', [
                        'loan_application' => $application,
                        'workspace' => 'profiles',
                    ]),
                    'tone' => 'attention',
                ];
            } elseif (is_array($collateral)) {
                $rows[] = [
                    'label' => 'Collateral',
                    'detail' => 'Complete',
                    'href' => null,
                    'tone' => 'quiet',
                ];
            } else {
                $rows[] = [
                    'label' => 'Collateral',
                    'detail' => 'Not required',
                    'href' => null,
                    'tone' => 'quiet',
                ];
            }
        } catch (\Throwable) {
            // Collateral service may not apply to every product.
        }

        $participants = [];
        if ($application->customer) {
            $participants[] = 'Borrower';
        }
        $guarantorCount = $application->customerGuarantors?->count() ?? 0;
        if ($guarantorCount > 0) {
            $participants[] = $guarantorCount === 1 ? '1 guarantor' : $guarantorCount.' guarantors';
        }
        if ($application->loanGroup) {
            $memberCount = $application->loanGroup->members?->count() ?? 0;
            $participants[] = 'Group · '.$memberCount.' members';
        }
        if ($participants !== []) {
            $rows[] = [
                'label' => 'Participants',
                'detail' => implode(' · ', $participants),
                'href' => route('admin.loan-applications.show', [
                    'loan_application' => $application,
                    'workspace' => 'profiles',
                ]),
                'tone' => 'quiet',
            ];
        }

        if (! empty($next['gate_label']) && ($next['source'] ?? '') === 'screening') {
            $rows[] = [
                'label' => 'Screening',
                'detail' => $next['gate_label'],
                'href' => $next['href'] ?? null,
                'tone' => in_array($next['cta_kind'] ?? '', ['waiting'], true) ? 'attention' : 'current',
            ];
        }

        if ($this->offers->offerDeclinedByBorrower($application)) {
            $rows[] = [
                'label' => 'Offer declined by member',
                'detail' => null,
                'href' => route('admin.loan-applications.show', $application),
                'tone' => 'attention',
            ];
        } elseif ((string) ($application->offer_status ?? '') === 'pending_borrower'
            || (string) $application->status === 'awaiting_offer') {
            $rows[] = [
                'label' => 'Offer awaiting member acceptance',
                'detail' => null,
                'href' => route('admin.loan-applications.show', $application),
                'tone' => 'attention',
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, mixed>|null  $stageHistory
     * @return list<array{at: string, label: string}>
     */
    private function timeline(LoanApplication $application, ?Collection $stageHistory): array
    {
        $events = [];

        if ($application->created_at) {
            $events[] = [
                'at' => $application->created_at->format('d M Y g:i A'),
                'label' => 'Application created',
                'sort' => $application->created_at->timestamp,
            ];
        }
        if ($application->submitted_at) {
            $events[] = [
                'at' => $application->submitted_at->format('d M Y g:i A'),
                'label' => 'Application submitted',
                'sort' => $application->submitted_at->timestamp,
            ];
        }

        foreach (($stageHistory ?? collect())->sortBy('created_at') as $row) {
            $from = $row->from_stage ?? null;
            $to = $row->to_stage ?? $row->stage ?? null;
            $label = $to
                ? ($from
                    ? $this->workflow->stageLabel((string) $from).' → '.$this->workflow->stageLabel((string) $to)
                    : 'Stage: '.$this->workflow->stageLabel((string) $to))
                : 'Stage update';
            $at = $row->created_at ?? $row->changed_at ?? null;
            if (! $at) {
                continue;
            }
            $events[] = [
                'at' => $at->format('d M Y g:i A'),
                'label' => $label,
                'sort' => $at->timestamp,
            ];
        }

        if ($application->offer_sent_at) {
            $events[] = [
                'at' => $application->offer_sent_at->format('d M Y g:i A'),
                'label' => 'Offer issued',
                'sort' => $application->offer_sent_at->timestamp,
            ];
        }
        if ($application->offer_accepted_at) {
            $events[] = [
                'at' => $application->offer_accepted_at->format('d M Y g:i A'),
                'label' => 'Offer accepted',
                'sort' => $application->offer_accepted_at->timestamp,
            ];
        }
        if ($application->loan?->disbursed_at) {
            $events[] = [
                'at' => $application->loan->disbursed_at->format('d M Y g:i A'),
                'label' => 'Disbursement released',
                'sort' => $application->loan->disbursed_at->timestamp,
            ];
        }

        usort($events, fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return array_map(
            fn ($e) => ['at' => $e['at'], 'label' => $e['label']],
            array_slice($events, -12)
        );
    }

    /**
     * @param  array<string, mixed>  $next
     */
    private function overallStatus(array $next, LoanApplication $application): string
    {
        if ($application->isClosed()) {
            return 'Closed';
        }
        if ($application->hasActiveFacility()) {
            return 'Active facility';
        }

        return match ($next['cta_kind'] ?? '') {
            'waiting' => 'Waiting',
            'decision', 'disburse' => 'Ready',
            'completed' => 'Complete',
            default => 'Needs attention',
        };
    }
}
