<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\LoanApplicationDraft;
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
        private readonly ProfileCompletionService $profileCompletion,
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
            'customerGuarantors.invitation.guarantorCustomer',
            'customerGuarantors.guarantor',
            'collateralAssets', 'assignedAnalyst',
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
        $people = $this->people($application, $actor, $next);
        $participants = $this->participantsFromPeople($people, (bool) $application->loanGroup);
        $readiness = $this->readiness($application, $documentRequests, $next, $lifecycle);

        $statusLabel = display_label($application->status, 'application_status');
        if ($statusLabel === '' || $statusLabel === (string) $application->status) {
            $statusLabel = str_replace('_', ' ', ucfirst((string) $application->status));
        }

        return [
            'application_number' => $application->application_number,
            'member_name' => $application->partyLabel(),
            'member_no' => $customer?->member_no,
            'member_url' => $customer
                ? route('admin.customers.show', $customer)
                : null,
            'is_group' => (bool) $application->loanGroup,
            'product_name' => $product?->name,
            'product_code' => $product?->code,
            'amount' => $amount,
            'amount_label' => $amount > 0 ? format_money($amount) : '—',
            'stage' => $stage,
            'stage_label' => $this->workflow->stageLabel($stage),
            'status' => (string) $application->status,
            'status_label' => $statusLabel,
            'gate_label' => $next['gate_label'] ?? null,
            'progress_percent' => $next['percent'] ?? null,
            'overall_status' => $this->overallStatus($next, $application),
            'next' => $next,
            'lifecycle' => $lifecycle,
            'attention' => $attention,
            'people' => $people,
            'participants' => $participants,
            'readiness' => $readiness,
            'timeline' => $this->timeline($application, $stageHistory),
        ];
    }

    /**
     * Application 360 for incomplete drafts — same hierarchy as submitted files.
     * Uses LoanApplicationDraftService snapshot; no second workflow engine.
     *
     * @param  array<string, mixed>|null  $snapshot
     * @param  array{label?: string, tone?: string}|null  $badge
     * @return array<string, mixed>
     */
    public function forDraft(
        LoanApplicationDraft $draft,
        ?array $snapshot = null,
        ?array $badge = null,
    ): array {
        $drafts = app(LoanApplicationDraftService::class);
        $draft->loadMissing(['customer', 'product']);
        $snapshot ??= $drafts->adminSnapshot($draft);
        $badge ??= $drafts->statusBadge($draft);
        $customer = $draft->customer;
        $product = $draft->product;
        $amount = $drafts->requestedAmount($draft) ?? 0.0;
        $percent = (int) ($snapshot['application_completion_percent'] ?? 0);
        $profileComplete = ! empty($snapshot['profile_information']['complete']);
        $guarantorStatus = (string) ($snapshot['guarantor']['status'] ?? $snapshot['guarantor_status'] ?? '');
        $waitingOnGuarantor = str_contains(strtolower($guarantorStatus), 'pending')
            || str_contains(strtolower($guarantorStatus), 'await')
            || str_contains(strtolower($guarantorStatus), 'not started');

        $missing = (string) ($snapshot['current_step'] ?? $drafts->progressLabel($draft));
        $who = 'Borrower';
        if ($waitingOnGuarantor && ! in_array(strtolower($guarantorStatus), ['not required', 'approved', ''], true)) {
            $missing = 'Waiting for guarantor — '.$guarantorStatus;
            $who = 'Guarantor';
        } elseif (! $profileComplete) {
            $missing = 'Profile information still incomplete';
        }

        $href = $customer
            ? route('admin.customers.show', $customer)
            : route('admin.loan-applications.incomplete');

        $people = $this->draftPeople($draft, $snapshot, $guarantorStatus, $waitingOnGuarantor);
        $isGroup = $this->draftIsGroup($draft, $snapshot);
        $participants = $this->participantsFromPeople($people, $isGroup);

        $lifecycle = [];
        $readiness = [];
        foreach ($snapshot['journey_steps'] ?? [] as $step) {
            $readiness[] = [
                'key' => (string) ($step['key'] ?? 'step'),
                'label' => (string) ($step['label'] ?? 'Step'),
                'state' => ! empty($step['complete']) ? 'complete' : (! empty($step['current']) ? 'current' : 'upcoming'),
                'detail' => ! empty($step['current']) ? $missing : null,
                'href' => null,
            ];
        }

        $ref = trim((string) ($draft->draft_reference ?? ''));
        $appNumber = $ref !== '' ? $ref : ('Draft #'.$draft->id);
        $timeline = [];
        if ($draft->created_at) {
            $timeline[] = ['at' => $draft->created_at->format('d M Y H:i'), 'label' => 'Draft started'];
        }
        $last = $snapshot['last_activity'] ?? $draft->saved_at ?? $draft->updated_at;
        if ($last) {
            $timeline[] = [
                'at' => \Illuminate\Support\Carbon::parse($last)->format('d M Y H:i'),
                'label' => 'Last borrower activity',
            ];
        }

        $email = $customer?->email;
        if (is_string($email) && str_contains($email, '@phone.kopafasta.local')) {
            $email = null;
        }

        return [
            'application_number' => $appNumber,
            'member_name' => $customer?->full_name ?: trim(($customer?->first_name.' '.$customer?->last_name) ?: '—'),
            'member_no' => $customer?->member_no,
            'member_url' => $customer ? route('admin.customers.show', $customer) : null,
            'member_phone' => $customer?->phone,
            'member_email' => $email,
            'is_group' => $isGroup,
            'is_draft' => true,
            'product_name' => $product?->name,
            'product_code' => $product?->code,
            'amount' => $amount,
            'amount_label' => $amount > 0 ? format_money($amount) : '—',
            'stage' => 'incomplete',
            'stage_label' => 'Incomplete application',
            'status' => 'incomplete',
            'status_label' => (string) ($badge['label'] ?? 'In progress'),
            'gate_label' => null,
            'progress_percent' => $percent,
            'overall_status' => 'Incomplete — not submitted',
            'next' => [
                'source' => 'draft',
                'headline' => $missing,
                'missing' => $missing,
                'who' => $who,
                'deadline' => null,
                'cta' => 'Continue application',
                'href' => $href,
                'cta_kind' => $waitingOnGuarantor ? 'waiting' : 'continue',
                'gate_label' => null,
                'percent' => $percent,
                'bucket' => 'do_now',
                'subjects' => [],
            ],
            'lifecycle' => $lifecycle,
            'attention' => [],
            'people' => $people,
            'participants' => $participants,
            'readiness' => $readiness,
            'timeline' => $timeline,
            'draft_snapshot' => $snapshot,
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

        if ((string) $application->status === 'awaiting_guarantor'
            || $stage === 'awaiting_guarantor') {
            $href = $application->customer
                ? route('admin.customers.show', ['customer' => $application->customer, 'tab' => 'applications']).'#member-file'
                : route('admin.loan-applications.show', $application);

            return [
                'source' => 'guarantor',
                'headline' => 'Waiting for guarantor',
                'missing' => 'Waiting for guarantor',
                'who' => 'Guarantor',
                'deadline' => null,
                'cta' => 'View guarantor',
                'href' => $href,
                'cta_kind' => 'waiting',
                'gate_label' => null,
                'percent' => null,
                'bucket' => 'waiting',
                'subjects' => [],
            ];
        }

        if (in_array($stage, $screeningStages, true)
            || in_array((string) $application->status, ['pending_documents', 'under_review'], true)) {
            try {
                $row = $this->screeningNext->forApplication($application, $actor);
            } catch (\Throwable) {
                return [
                    'source' => 'screening',
                    'headline' => 'Continue Screening',
                    'missing' => 'Continue Screening',
                    'who' => 'Staff',
                    'deadline' => null,
                    'cta' => 'Continue Screening',
                    'href' => route('admin.loan-applications.guided-screening', $application),
                    'cta_kind' => 'continue',
                    'gate_label' => null,
                    'percent' => null,
                    'bucket' => null,
                    'subjects' => [],
                ];
            }
            $ctaKind = (string) ($row['cta_kind'] ?? 'continue');
            $href = match ($ctaKind) {
                'waiting' => (string) ($row['desk_href'] ?? $row['href'] ?? '#'),
                'decision' => (string) ($row['href'] ?? $row['desk_href'] ?? '#'),
                default => (string) ($row['review_href'] ?? $row['href'] ?? '#'),
            };

            $waiting = is_array($row['waiting'] ?? null) ? $row['waiting'] : null;

            return [
                'source' => 'screening',
                'headline' => $this->plainText($row['what_happens_next'] ?? $row['cta'] ?? 'Continue Screening', 'Continue Screening'),
                'missing' => $this->plainText($row['what_happens_next'] ?? $row['cta'] ?? 'Continue Screening', 'Continue Screening'),
                'who' => $this->plainText($waiting['label'] ?? $row['participant'] ?? 'Staff', 'Staff'),
                'deadline' => $waiting['deadline'] ?? $waiting['due'] ?? null,
                'cta' => $this->plainText($row['cta'] ?? 'Continue Screening', 'Continue Screening'),
                'href' => $href,
                'cta_kind' => $ctaKind,
                'gate_label' => $this->plainText($row['current_gate_label'] ?? $row['gate_label'] ?? '', ''),
                'percent' => $row['percent'] ?? null,
                'bucket' => $row['bucket'] ?? null,
                'subjects' => $row['subjects'] ?? [],
            ];
        }

        if (in_array($stage, $committeeStages, true)) {
            $row = $this->guidedApproval->committeeNext($application);

            return [
                'source' => 'committee',
                'headline' => (string) ($row['what_happens_next'] ?? $row['cta'] ?? 'Committee review'),
                'missing' => (string) ($row['what_happens_next'] ?? $row['cta'] ?? 'Committee review'),
                'who' => 'Credit committee',
                'deadline' => null,
                'cta' => (string) ($row['cta'] ?? 'Continue Committee Review'),
                'href' => (string) ($row['review_href'] ?? $row['href'] ?? '#'),
                'cta_kind' => ($row['bucket'] ?? '') === 'waiting' ? 'waiting' : 'continue',
                'gate_label' => null,
                'percent' => null,
                'bucket' => $row['bucket'] ?? null,
                'subjects' => [],
            ];
        }

        if (in_array($stage, $postStages, true) || $application->hasActiveFacility()) {
            if ($application->hasActiveFacility()) {
                return [
                    'source' => 'servicing',
                    'headline' => 'Facility is live — manage from Credit management',
                    'missing' => 'No outstanding origination blockers',
                    'who' => 'Credit operations',
                    'deadline' => null,
                    'cta' => 'View credit management',
                    'href' => route('admin.loan-applications.show', $application),
                    'cta_kind' => 'completed',
                    'gate_label' => null,
                    'percent' => 100,
                    'bucket' => 'completed',
                    'subjects' => [],
                ];
            }

            $row = $this->postApprovalNext->forApplication($application, $actor);
            $ctaKind = (string) ($row['cta_kind'] ?? 'continue');

            return [
                'source' => 'post_approval',
                'headline' => (string) ($row['next_action'] ?? $row['cta'] ?? 'Continue Post-Approval'),
                'missing' => (string) ($row['next_action'] ?? $row['cta'] ?? 'Continue Post-Approval'),
                'who' => (string) ($row['waiting_on'] ?? $row['who'] ?? 'Staff'),
                'deadline' => $row['deadline'] ?? null,
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
                'subjects' => [],
            ];
        }

        if ($application->isClosed()) {
            return [
                'source' => 'closed',
                'headline' => $application->closedReasonLabel() ?: 'File closed',
                'missing' => $application->closedReasonLabel() ?: 'File closed',
                'who' => '—',
                'deadline' => null,
                'cta' => 'View file',
                'href' => route('admin.loan-applications.show', $application),
                'cta_kind' => 'completed',
                'gate_label' => null,
                'percent' => null,
                'bucket' => 'completed',
                'subjects' => [],
            ];
        }

        return [
            'source' => 'file',
            'headline' => 'Open the credit file',
            'missing' => 'Review the application file',
            'who' => 'Staff',
            'deadline' => null,
            'cta' => 'View Overview',
            'href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ]),
            'cta_kind' => 'continue',
            'gate_label' => null,
            'percent' => null,
            'bucket' => null,
            'subjects' => [],
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
     * @return list<array<string, mixed>>
     */
    private function people(LoanApplication $application, ?User $actor, array $next): array
    {
        $subjects = is_array($next['subjects'] ?? null) ? $next['subjects'] : [];
        $borrowerId = (int) ($application->customer_id ?? 0);
        if ($subjects !== []) {
            $cards = [];
            foreach ($subjects as $subject) {
                $role = (string) ($subject['role'] ?? ucfirst((string) ($subject['person'] ?? 'participant')));
                $customerId = $subject['customer_id'] ?? null;
                if (strcasecmp($role, 'Guarantor') === 0 && (int) $customerId === $borrowerId) {
                    $customerId = null;
                }
                $customer = $customerId
                    ? \App\Models\Customer::query()->find($customerId)
                    : null;
                $issue = null;
                if ((int) ($subject['failed'] ?? 0) > 0) {
                    $issue = $subject['failed'].' finding(s) need attention';
                } elseif (! ($subject['complete'] ?? false)) {
                    $done = (int) ($subject['done'] ?? 0);
                    $total = (int) ($subject['total'] ?? 0);
                    $issue = $total > 0 ? "Screening {$done}/{$total}" : 'Screening in progress';
                }
                $gate3 = $subject['gate3']['label'] ?? ($subject['gate3']['chip'] ?? null);
                $card = $customer
                    ? $this->customerCard($customer, (string) ($subject['role'] ?? ucfirst((string) ($subject['person'] ?? 'participant'))))
                    : [
                        'name' => (string) ($subject['label'] ?? 'Participant'),
                        'role' => (string) ($subject['role'] ?? ucfirst((string) ($subject['person'] ?? 'participant'))),
                        'href' => $subject['href'] ?? null,
                        'tone' => 'attention',
                    ];
                $card['crb'] = $gate3 ? (string) $gate3 : ($card['crb'] ?? '—');
                if ($issue && empty($card['issue'])) {
                    $card['issue'] = $issue;
                    $card['readiness'] = ($subject['complete'] ?? false) ? 'Ready' : $issue;
                    $card['tone'] = ($subject['complete'] ?? false)
                        ? 'complete'
                        : (((int) ($subject['failed'] ?? 0) > 0) ? 'attention' : ($card['tone'] ?? 'current'));
                }
                $cards[] = $card;
            }

            $this->appendGuarantors($application, $cards);

            return $cards;
        }

        $cards = [];
        if ($application->loanGroup) {
            $group = $application->loanGroup;
            $index = 2;
            foreach ($group->members ?? [] as $member) {
                $customer = $member->customer;
                if (! $customer) {
                    continue;
                }
                $isLeader = (int) ($group->leader_id ?? 0) === (int) $member->id
                    || (int) ($group->leader_customer_id ?? 0) === (int) $customer->id;
                $role = $isLeader ? 'Leader' : 'Member '.$index;
                if (! $isLeader) {
                    $index++;
                }
                $cards[] = $this->customerCard($customer, $role);
            }
        } elseif ($application->customer) {
            $cards[] = $this->customerCard($application->customer, 'Borrower');
        }

        $this->appendGuarantors($application, $cards);

        return $cards;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    private function appendGuarantors(LoanApplication $application, array &$cards): void
    {
        $existing = collect($cards)
            ->filter(fn ($card) => strcasecmp((string) ($card['role'] ?? ''), 'Guarantor') === 0)
            ->pluck('customer_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($application->customerGuarantors ?? [] as $link) {
            $card = $this->guarantorCard($application, $link);
            $id = (int) ($card['customer_id'] ?? 0);
            if ($id > 0 && in_array($id, $existing, true)) {
                continue;
            }
            if ($id === 0 && collect($cards)->contains(fn ($row) => strcasecmp((string) ($row['name'] ?? ''), (string) ($card['name'] ?? '')) === 0
                && strcasecmp((string) ($row['role'] ?? ''), 'Guarantor') === 0)) {
                continue;
            }
            $cards[] = $card;
            if ($id > 0) {
                $existing[] = $id;
            }
        }
    }

    /**
     * Same identity resolution as Member 360 applications: invitation invitee / guarantor customer.
     * CustomerGuarantor.customer is the borrower — never treat that as the guarantor.
     *
     * @return array<string, mixed>
     */
    private function guarantorCard(LoanApplication $application, \App\Models\CustomerGuarantor $link): array
    {
        $invite = $link->invitation;
        $gCustomer = $invite?->guarantorCustomer;
        $borrowerId = (int) ($application->customer_id ?? 0);
        if ($gCustomer && (int) $gCustomer->id === $borrowerId) {
            $gCustomer = null;
        }

        $name = trim((string) (
            $invite?->invitee_name
            ?? $gCustomer?->full_name
            ?? $link->displayName()
            ?? 'Guarantor'
        ));
        if ($name === '' || strcasecmp($name, 'Guarantor') === 0) {
            $name = 'Guarantor';
        }

        if ($gCustomer) {
            $card = $this->customerCard($gCustomer, 'Guarantor');
            if ($name !== 'Guarantor') {
                $card['name'] = $name;
            }

            return $card;
        }

        return [
            'key' => 'guarantor-'.($link->id ?? 'pending'),
            'kind' => 'person',
            'customer_id' => null,
            'name' => $name,
            'role' => 'Guarantor',
            'kyc' => '0%',
            'completion_percent' => 0,
            'completed_areas' => [],
            'missing_areas' => ['Waiting for guarantor'],
            'completion_cards' => [[
                'key' => 'profile',
                'label' => 'Profile',
                'complete' => false,
            ]],
            'crb' => '—',
            'readiness' => 'Waiting',
            'issue' => 'Waiting for guarantor',
            'href' => $application->customer
                ? route('admin.customers.show', ['customer' => $application->customer, 'tab' => 'applications']).'#member-file'
                : route('admin.loan-applications.show', $application),
            'tone' => 'attention',
            'documents' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerCard(\App\Models\Customer $customer, string $role, ?string $key = null): array
    {
        $summary = $this->profileCompletion->completionSummary($customer);
        $calculated = $this->profileCompletion->calculate($customer);
        $percent = (int) ($summary['percent'] ?? $calculated['percent'] ?? 0);
        $completed = array_values(array_filter(
            $summary['completed'] ?? [],
            fn ($label) => ! $this->isRetiredVerificationLabel((string) $label),
        ));
        $missing = array_values(array_filter(
            $summary['remaining'] ?? [],
            fn ($label) => ! $this->isRetiredVerificationLabel((string) $label),
        ));
        $needsAttention = $percent < 100 || $missing !== [];
        $completionCards = [];
        foreach ($calculated['sections'] ?? [] as $section) {
            $label = (string) ($section['label'] ?? $section['key'] ?? '');
            if ($this->isRetiredVerificationLabel($label) || $this->isRetiredVerificationLabel((string) ($section['key'] ?? ''))) {
                continue;
            }
            $completionCards[] = [
                'key' => (string) ($section['key'] ?? ''),
                'label' => $label,
                'complete' => ! empty($section['complete']),
            ];
        }

        return [
            'key' => $key ?: ('customer-'.$customer->id),
            'kind' => 'person',
            'customer_id' => (int) $customer->id,
            'name' => trim($customer->full_name ?: ($customer->first_name.' '.$customer->last_name)) ?: 'Member',
            'role' => $role,
            'kyc' => $percent.'%',
            'completion_percent' => $percent,
            'completed_areas' => $completed,
            'missing_areas' => $missing,
            'completion_cards' => $completionCards,
            'crb' => '—',
            'readiness' => $needsAttention ? 'Needs attention' : 'Ready',
            'issue' => $needsAttention ? (implode(', ', array_slice($missing, 0, 3)) ?: 'Profile incomplete') : null,
            'href' => route('admin.customers.show', $customer),
            'tone' => $needsAttention ? 'attention' : 'complete',
            'documents' => $this->documentHolders($customer),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $people
     * @return list<array<string, mixed>>
     */
    private function participantsFromPeople(array $people, bool $isGroup): array
    {
        $persons = array_values(array_filter(
            $people,
            fn ($row) => ($row['kind'] ?? 'person') !== 'all' && ($row['role'] ?? '') !== 'Group',
        ));
        $out = [];
        if ($isGroup && count($persons) > 1) {
            $complete = count(array_filter($persons, fn ($row) => ($row['tone'] ?? '') === 'complete'));
            $attention = count($persons) - $complete;
            $out[] = [
                'key' => 'all',
                'kind' => 'all',
                'customer_id' => null,
                'name' => 'All',
                'role' => 'Group',
                'label' => 'All ('.count($persons).')',
                'kyc' => $complete.' / '.count($persons),
                'completion_percent' => count($persons) > 0 ? (int) round(($complete / count($persons)) * 100) : 0,
                'completed_areas' => [],
                'missing_areas' => [],
                'complete_count' => $complete,
                'attention_count' => $attention,
                'aggregate_label' => $complete.' complete · '.$attention.' need attention',
                'crb' => '—',
                'readiness' => $attention > 0 ? 'Needs attention' : 'Ready',
                'issue' => $attention > 0 ? $attention.' member'.($attention === 1 ? '' : 's').' need attention' : null,
                'href' => null,
                'tone' => $attention > 0 ? 'attention' : 'complete',
                'documents' => [],
            ];
        }

        return array_merge($out, $persons);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    private function draftPeople(LoanApplicationDraft $draft, array $snapshot, string $guarantorStatus, bool $waitingOnGuarantor): array
    {
        $people = [];
        $customer = $draft->customer;
        if ($customer) {
            $people[] = $this->customerCard($customer, 'Borrower');
        }

        $members = $this->draftGroupMembers($draft, $snapshot);
        if ($members !== []) {
            $index = 2;
            foreach ($members as $member) {
                if (! $member instanceof \App\Models\Customer) {
                    continue;
                }
                if ($customer && (int) $member->id === (int) $customer->id) {
                    $people[0]['role'] = 'Leader';
                    continue;
                }
                $people[] = $this->customerCard($member, 'Member '.$index);
                $index++;
            }
        }

        $gName = $snapshot['guarantor']['name'] ?? null;
        $gCustomerId = $snapshot['guarantor']['customer_id'] ?? $snapshot['guarantor']['id'] ?? null;
        if (filled($gName) || ($guarantorStatus !== '' && strtolower($guarantorStatus) !== 'not required')) {
            $gCustomer = is_numeric($gCustomerId) ? \App\Models\Customer::query()->find((int) $gCustomerId) : null;
            if ($gCustomer && $customer && (int) $gCustomer->id === (int) $customer->id) {
                $gCustomer = null;
            }
            if ($gCustomer) {
                $people[] = $this->customerCard($gCustomer, 'Guarantor');
            } else {
                $people[] = [
                    'key' => 'guarantor-draft',
                    'kind' => 'person',
                    'customer_id' => null,
                    'name' => (string) ($gName ?: 'Guarantor'),
                    'role' => 'Guarantor',
                    'kyc' => '0%',
                    'completion_percent' => 0,
                    'completed_areas' => [],
                    'missing_areas' => $waitingOnGuarantor ? [$guarantorStatus ?: 'Awaiting guarantor'] : [],
                    'completion_cards' => $waitingOnGuarantor ? [[
                        'key' => 'profile',
                        'label' => 'Profile',
                        'complete' => false,
                    ]] : [],
                    'crb' => '—',
                    'readiness' => $waitingOnGuarantor ? 'Needs attention' : 'Ready',
                    'issue' => $waitingOnGuarantor ? $guarantorStatus : null,
                    'href' => $customer ? route('admin.customers.show', $customer) : null,
                    'tone' => $waitingOnGuarantor ? 'attention' : 'complete',
                    'documents' => [],
                ];
            }
        }

        return $people;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<\App\Models\Customer>
     */
    private function draftGroupMembers(LoanApplicationDraft $draft, array $snapshot): array
    {
        $payload = $draft->payload ?? [];
        $raw = $payload['group_members'] ?? $payload['form']['group_members'] ?? $snapshot['group_members'] ?? [];
        if (! is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $row) {
            $id = is_array($row)
                ? ($row['customer_id'] ?? $row['id'] ?? null)
                : $row;
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        return \App\Models\Customer::query()->whereIn('id', $ids)->get()->all();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function draftIsGroup(LoanApplicationDraft $draft, array $snapshot): bool
    {
        $product = $draft->product;
        if ($product && (str_starts_with(strtoupper((string) $product->code), 'GL') || ($product->category ?? '') === 'group')) {
            return true;
        }

        return $this->draftGroupMembers($draft, $snapshot) !== [];
    }

    /**
     * Canonical Profile Document Holder groups — one current document per type code.
     *
     * @return list<array<string, mixed>>
     */
    private function documentHolders(\App\Models\Customer $customer): array
    {
        $customer->loadMissing(['documents.documentType']);
        $grouped = [];
        foreach ($customer->documents as $doc) {
            $type = $doc->documentType;
            $code = strtolower((string) ($type?->code ?: ($type?->name ?: 'doc-'.$doc->id)));
            $grouped[$code][] = $doc;
        }

        $rows = [];
        foreach ($grouped as $code => $docs) {
            usort($docs, fn ($a, $b) => ($b->created_at?->timestamp ?? 0) <=> ($a->created_at?->timestamp ?? 0));
            $current = $docs[0];
            $status = match ((string) ($current->status ?? '')) {
                'rejected', 'revision_required', 'needs_replacement' => 'needs_replacement',
                default => filled($current->file_path) ? 'present' : 'missing',
            };
            $url = $current->file_path ? asset('storage/'.$current->file_path) : null;
            $label = $current->documentType?->name ?? 'Document';
            $category = $this->documentHolderCategory($code, $label, (string) ($current->documentType?->category ?? ''));
            $history = [];
            foreach (array_slice($docs, 1) as $older) {
                if (! filled($older->file_path)) {
                    continue;
                }
                $history[] = [
                    'key' => 'doc-'.$older->id,
                    'url' => asset('storage/'.$older->file_path),
                    'uploaded_at' => $older->created_at?->timezone(config('app.timezone'))->format('d M Y'),
                ];
            }
            $rows[] = [
                'key' => 'doc-'.$current->id,
                'type_code' => $code,
                'category' => $category,
                'label' => $label,
                'status' => $status,
                'url' => $url,
                'eyebrow' => match ($status) {
                    'present' => 'Present',
                    'needs_replacement' => 'Needs replacement',
                    default => 'Missing',
                },
                'uploaded_at' => $current->created_at?->timezone(config('app.timezone'))->format('d M Y'),
                'kind' => str_contains(strtolower((string) $current->file_path), '.pdf') ? 'pdf' : 'image',
                'history' => $history,
            ];
        }

        foreach ($this->profileCompletion->sectionGaps($customer, 'kyc') as $gap) {
            $label = (string) ($gap['label'] ?? 'Document');
            $code = strtolower((string) ($gap['key'] ?? $label));
            $already = collect($rows)->contains(
                fn ($row) => $row['type_code'] === $code || strcasecmp((string) $row['label'], $label) === 0
            );
            if ($already) {
                continue;
            }
            $rows[] = [
                'key' => 'missing-'.$code,
                'type_code' => $code,
                'category' => $this->documentHolderCategory($code, $label, ''),
                'label' => $label,
                'status' => 'missing',
                'url' => null,
                'eyebrow' => 'Missing',
                'uploaded_at' => null,
                'kind' => 'image',
                'history' => [],
            ];
        }

        $order = ['identity' => 0, 'residence' => 1, 'financial' => 2, 'business' => 3, 'collateral' => 4, 'other' => 5];
        usort($rows, function ($a, $b) use ($order) {
            $ca = $order[$a['category'] ?? 'other'] ?? 5;
            $cb = $order[$b['category'] ?? 'other'] ?? 5;
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }

            return strcasecmp((string) $a['label'], (string) $b['label']);
        });

        return $rows;
    }

    /**
     * Map a stored document onto the existing Profile holder categories.
     */
    private function documentHolderCategory(string $code, string $label, string $storedCategory): string
    {
        $hay = strtolower(trim($code.' '.$label.' '.$storedCategory));
        if (str_contains($hay, 'national') || str_contains($hay, 'nida') || str_contains($hay, 'passport')
            || str_contains($hay, 'voter') || str_contains($hay, 'driving') || str_contains($hay, 'identity')
            || str_contains($hay, 'selfie') || str_contains($hay, 'face')) {
            return 'identity';
        }
        if (str_contains($hay, 'residence') || str_contains($hay, 'lga') || str_contains($hay, 'utility')
            || str_contains($hay, 'tenancy') || str_contains($hay, 'letter')) {
            return 'residence';
        }
        if (str_contains($hay, 'bank') || str_contains($hay, 'mobile_money') || str_contains($hay, 'mobile money')
            || str_contains($hay, 'salary') || str_contains($hay, 'income') || str_contains($hay, 'statement')) {
            return 'financial';
        }
        if (str_contains($hay, 'business') || str_contains($hay, 'licence') || str_contains($hay, 'license')
            || str_contains($hay, 'tin') || str_contains($hay, 'brela')) {
            return 'business';
        }
        if (str_contains($hay, 'collateral') || str_contains($hay, 'asset') || str_contains($hay, 'vehicle')
            || str_contains($hay, 'ownership') || str_contains($hay, 'insurance') || str_contains($hay, 'logbook')) {
            return 'collateral';
        }

        return 'other';
    }

    /**
     * @param  Collection<int, LoanApplicationDocumentRequest>|null  $documentRequests
     * @param  array<string, mixed>  $next
     * @param  list<array<string, mixed>>  $lifecycle
     * @return list<array{key: string, label: string, state: string, detail: ?string, href: ?string}>
     */
    private function readiness(
        LoanApplication $application,
        ?Collection $documentRequests,
        array $next,
        array $lifecycle,
    ): array {
        $byKey = collect($lifecycle)->keyBy('key');
        $openDocs = ($documentRequests ?? collect())
            ->filter(fn ($req) => ! in_array((string) ($req->status ?? ''), ['satisfied', 'cancelled', 'closed'], true));

        $collateralDetail = 'N/A';
        $collateralState = 'na';
        try {
            $collateral = $this->collateralSecure->state($application);
            if (is_array($collateral) && $this->collateralSecure->isOpen($application)) {
                $collateralState = 'attention';
                $collateralDetail = (string) ($collateral['status'] ?? 'Outstanding');
            } elseif (is_array($collateral)) {
                $collateralState = 'complete';
                $collateralDetail = 'Complete';
            }
        } catch (\Throwable) {
            // leave N/A
        }

        $feeStatus = (string) ($application->application_fee_status ?? '');
        $feeState = match (true) {
            $feeStatus === '' => 'na',
            in_array($feeStatus, ['paid', 'waived', 'charged'], true) => 'complete',
            default => 'attention',
        };

        $rows = [
            [
                'key' => 'application',
                'label' => 'Application & affordability',
                'state' => $feeState === 'attention' ? 'attention' : ($byKey->get('application')['state'] ?? 'upcoming'),
                'detail' => $feeState === 'attention'
                    ? 'Application fee outstanding'
                    : ($feeState === 'complete' ? 'Fee settled' : null),
                'href' => $byKey->get('application')['href'] ?? null,
            ],
            [
                'key' => 'screening',
                'label' => 'Screening & CRB',
                'state' => $byKey->get('screening')['state'] ?? 'upcoming',
                'detail' => $next['gate_label'] ?? null,
                'href' => $byKey->get('screening')['href'] ?? null,
            ],
            [
                'key' => 'documents',
                'label' => 'Documents',
                'state' => $openDocs->isNotEmpty() ? 'attention' : 'complete',
                'detail' => $openDocs->isNotEmpty()
                    ? ($openDocs->count().' outstanding')
                    : 'Complete',
                'href' => route('admin.loan-applications.show', [
                    'loan_application' => $application,
                    'workspace' => 'checklist',
                ]),
            ],
            [
                'key' => 'collateral',
                'label' => 'Collateral / security',
                'state' => $collateralState,
                'detail' => $collateralDetail,
                'href' => $collateralState === 'na' ? null : route('admin.loan-applications.show', [
                    'loan_application' => $application,
                    'workspace' => 'profiles',
                ]),
            ],
        ];

        foreach (['decision', 'committee', 'offer', 'post_approval', 'contract', 'disbursement'] as $key) {
            $step = $byKey->get($key);
            $rows[] = [
                'key' => $key,
                'label' => match ($key) {
                    'post_approval' => 'Post-Approval',
                    default => ucfirst(str_replace('_', '-', $key)),
                },
                'state' => $step['state'] ?? 'upcoming',
                'detail' => null,
                'href' => $step['href'] ?? null,
            ];
        }

        return $rows;
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

    private function isRetiredVerificationLabel(string $value): bool
    {
        $hay = strtolower($value);

        return str_contains($hay, 'face verification')
            || str_contains($hay, 'face photos')
            || str_contains($hay, 'nida verification')
            || str_contains($hay, 'face_verification')
            || $hay === 'face'
            || str_contains($hay, 'selfie');
    }

    private function plainText(mixed $value, string $fallback): string
    {
        if (is_array($value)) {
            foreach (['label', 'text', 'headline', 'cta', 0] as $key) {
                if (isset($value[$key]) && ! is_array($value[$key]) && filled($value[$key])) {
                    return (string) $value[$key];
                }
            }

            return $fallback;
        }

        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $fallback;
    }
}
