<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanProductRequirement;
use App\Models\User;

/**
 * Layperson-facing readiness + suggested screening decision from checklist, CRB, and affordability.
 */
class ScreeningReadinessService
{
    public function __construct(
        private readonly ScreeningChecklistService $checklist,
        private readonly CrbCreditCheckService $crb,
    ) {}

    /**
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>|null  $groupReview
     * @param  list<array<string, mixed>>  $anomalies
     * @return array{
     *   ready: bool,
     *   suggestion: string,
     *   suggestion_label: string,
     *   headline: string,
     *   detail: string,
     *   tone: string,
     *   blockers: list<string>,
     *   signals: list<string>,
     *   next_steps: list<array{label: string, detail: string, href: string, tone: string}>,
     *   critical_fails: list<string>,
     *   checklist_done: int,
     *   checklist_total: int,
     *   checklist_failed: int,
     *   subjects_incomplete: int,
     *   subjects_total: int,
     *   na_note: string,
     * }
     */
    public function forApplication(
        LoanApplication $application,
        array $review,
        ?array $groupReview = null,
        array $anomalies = [],
        ?User $actor = null,
    ): array {
        $actor = $actor ?? auth()->user();
        $subjects = $this->checklist->deskSubjects($application, $review, $groupReview, $actor);

        $checklistFailed = 0;
        $humanDone = 0;
        $humanTotal = 0;
        $incomplete = [];
        $failedSubjects = [];
        $nextSteps = [];
        $criticalFails = [];
        $criticalFailCount = 0;
        $autoCompleted = [];
        $gateAgg = [];
        $memberSummaries = [];
        $gateService = app(ScreeningChecklistGateService::class);

        foreach ($subjects as $subject) {
            $desk = $this->checklist->deskViewModel(
                $application,
                $review,
                $groupReview,
                $actor,
                (string) ($subject['person'] ?? 'borrower'),
                isset($subject['g']) ? (int) $subject['g'] : null,
                isset($subject['m']) ? (int) $subject['m'] : null,
            );

            $subjectHumanOpen = 0;
            $subjectHumanFail = 0;
            $subjectIssues = [];

            foreach ($desk['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    $verdict = $item['verdict'] ?? null;
                    $risk = (string) ($item['risk'] ?? 'normal');
                    $gate = (string) ($item['gate'] ?? '');
                    $isIncomeGate = $gate === 'statements_vs_declared'
                        || ($item['key'] ?? '') === 'activity_income.income_evidence';
                    $subjectLabel = trim(($subject['label'] ?? 'Subject').' · '.($subject['sublabel'] ?? ''));
                    $dest = $item['destination'] ?? $gateService->destination(
                        $application,
                        (string) ($item['key'] ?? ''),
                        $subject,
                    );
                    $href = (string) ($dest['href'] ?? '');
                    $cta = (string) ($dest['cta'] ?? 'Open check');
                    $uxGate = (string) ($item['ux_gate'] ?? $dest['gate'] ?? $gateService->gateFor((string) ($group['key'] ?? ''), (string) ($item['key'] ?? '')));
                    $quiet = $gateService->isQuietAuto($item);
                    $systemDetermined = $gateService->isSystemDetermined($item);
                    $detailFromRows = collect($item['evidence']['compare'] ?? [])
                        ->filter(fn ($row) => ($row['status'] ?? '') === 'mismatch')
                        ->map(fn ($row) => trim(($row['label'] ?? '').': '.($row['profile'] ?? '—').' vs '.($row['crb'] ?? '—')))
                        ->filter()
                        ->implode('; ');

                    if ($quiet) {
                        $autoCompleted[] = [
                            'label' => ($item['label'] ?? 'Check').($verdict === 'na' ? ' (N/A)' : ''),
                            'detail' => $this->autoCheckDetail($item, $subjectLabel),
                        ];

                        continue;
                    }

                    if ($systemDetermined) {
                        $reason = (string) ($item['fail_reason_label'] ?? $item['awaiting_message'] ?? '');
                        if ($verdict === 'fail' || $verdict === null) {
                            if ($verdict === 'fail') {
                                $subjectHumanFail++;
                                if ($risk === 'critical') {
                                    $criticalFailCount++;
                                    $criticalFails[] = ($item['label'] ?? 'Check').' ('.$subjectLabel.')';
                                }
                            }
                            $gateAgg[$uxGate] ??= ['decided' => 0, 'total' => 0, 'failed' => 0];
                            $gateAgg[$uxGate]['total']++;
                            if ($verdict !== null) {
                                $gateAgg[$uxGate]['decided']++;
                            }
                            if ($verdict === 'fail') {
                                $gateAgg[$uxGate]['failed']++;
                            }
                            $nextSteps[] = [
                                'label' => $item['label'] ?? 'Checklist item',
                                'detail' => trim($subjectLabel.($reason !== '' ? ' — '.$reason : '').($detailFromRows !== '' ? ' — '.$detailFromRows : '')),
                                'href' => $href,
                                'cta' => $cta,
                                'tone' => $verdict === 'fail' ? ($risk === 'critical' ? 'critical' : 'fail') : 'open',
                                'gate' => $dest['gate'] ?? $uxGate,
                                'dedupe_key' => (string) ($item['key'] ?? $item['label'] ?? ''),
                                'subject_key' => (string) ($subject['key'] ?? ''),
                            ];
                            $subjectIssues[] = $nextSteps[array_key_last($nextSteps)];
                        }

                        continue;
                    }

                    $humanTotal++;
                    if ($verdict !== null) {
                        $humanDone++;
                    } else {
                        $subjectHumanOpen++;
                    }
                    if ($verdict === 'fail') {
                        $subjectHumanFail++;
                    }

                    $gateAgg[$uxGate] ??= ['decided' => 0, 'total' => 0, 'failed' => 0];
                    $gateAgg[$uxGate]['total']++;
                    if ($verdict !== null) {
                        $gateAgg[$uxGate]['decided']++;
                    }
                    if ($verdict === 'fail') {
                        $gateAgg[$uxGate]['failed']++;
                    }

                    if ($verdict === null) {
                        $nextSteps[] = [
                            'label' => $isIncomeGate
                                ? 'Statement totals need review'
                                : (($item['label'] ?? 'Checklist item').(! empty($item['awaiting_data']) ? ' · Missing' : ' · Needs review')),
                            'detail' => $isIncomeGate
                                ? $subjectLabel.' · Enter statement totals and Save.'
                                : trim($subjectLabel.($detailFromRows !== '' ? ' — '.$detailFromRows : '')),
                            'href' => $href,
                            'cta' => $cta,
                            'tone' => $isIncomeGate ? 'gate' : ($risk === 'critical' ? 'critical' : 'open'),
                            'gate' => $isIncomeGate ? 'statements_vs_declared' : ($dest['gate'] ?? $uxGate),
                            'dedupe_key' => (string) ($item['key'] ?? $item['label'] ?? ''),
                        ];
                        $subjectIssues[] = $nextSteps[array_key_last($nextSteps)];
                    } elseif ($verdict === 'fail') {
                        if ($risk === 'critical') {
                            $criticalFailCount++;
                            $criticalFails[] = ($item['label'] ?? 'Check').' ('.$subjectLabel.')';
                        }
                        $reason = (string) ($item['fail_reason_label'] ?? '');
                        $nextSteps[] = [
                            'label' => $item['label'] ?? 'Checklist item',
                            'detail' => trim($subjectLabel.($reason !== '' ? ' — '.$reason : '').($detailFromRows !== '' ? ' — '.$detailFromRows : '')),
                            'href' => $href,
                            'cta' => $cta,
                            'tone' => $isIncomeGate ? 'gate' : ($risk === 'critical' ? 'critical' : 'fail'),
                            'gate' => $isIncomeGate ? 'statements_vs_declared' : ($dest['gate'] ?? $uxGate),
                            'dedupe_key' => (string) ($item['key'] ?? $item['label'] ?? ''),
                        ];
                        $subjectIssues[] = $nextSteps[array_key_last($nextSteps)];
                    }
                }
            }

            $checklistFailed += $subjectHumanFail;
            if ($subjectHumanOpen > 0) {
                $incomplete[] = trim(($subject['label'] ?? 'Subject').' · '.($subject['sublabel'] ?? ''));
            }
            if ($subjectHumanFail > 0) {
                $failedSubjects[] = trim(($subject['label'] ?? 'Subject').' · '.($subject['sublabel'] ?? ''));
            }

            $memberSummaries[] = $this->memberSummaryCard(
                $application,
                $subject,
                $groupReview,
                $subjectHumanOpen,
                $subjectHumanFail,
                $subjectIssues,
            );
        }

        $checklistDone = $humanDone;
        $checklistTotal = $humanTotal;

        $afford = (array) ($review['affordability'] ?? []);
        $affordVerdict = strtolower((string) ($afford['verdict'] ?? ''));
        if ($affordVerdict === '' && array_key_exists('pass', $afford)) {
            $affordVerdict = ($afford['pass'] ?? false) ? 'pass' : 'fail';
        }

        $crbSignal = $this->crbSignal($review, $groupReview);
        $criticalFlags = collect($anomalies)->where('severity', 'critical')->count();
        $warningFlags = collect($anomalies)->where('severity', 'warning')->count();

        $blockers = [];
        $signals = [];

        if ($incomplete !== []) {
            $blockers[] = 'Checklist incomplete for: '.implode(', ', array_slice($incomplete, 0, 4))
                .(count($incomplete) > 4 ? '…' : '');
        }
        if ($criticalFailCount > 0) {
            $blockers[] = $criticalFailCount.' high-risk Fail'
                .(count($criticalFails) > 0 ? ' — '.implode('; ', array_slice($criticalFails, 0, 3)) : '');
        }
        if ($checklistFailed > 0) {
            $blockers[] = $checklistFailed.' checklist Fail'
                .($failedSubjects !== [] ? ' ('.implode(', ', array_slice($failedSubjects, 0, 3)).')' : '');
        }
        if ($affordVerdict === 'fail') {
            $blockers[] = 'Affordability fails — borrower capacity does not support the proposed instalment';
            $borrower = collect($subjects)->firstWhere('person', 'borrower') ?? ($subjects[0] ?? null);
            if ($borrower && count($nextSteps) < 12) {
                $nextSteps[] = [
                    'label' => 'Affordability fail — review capacity numbers',
                    'detail' => 'Capacity → Affordability',
                    'href' => $this->checklistHref($application, $borrower, 'capacity', null, null, 'affordability'),
                    'tone' => 'critical',
                ];
            }
        } elseif ($affordVerdict === 'warn') {
            $signals[] = 'Affordability is near the limit (warn)';
        } elseif ($affordVerdict === 'pass') {
            $signals[] = 'Affordability pass';
        } else {
            $signals[] = 'Affordability not calculated yet';
        }

        $signals[] = 'CRB '.$crbSignal['label'].' → '.strtoupper($crbSignal['recommendation']);
        if ($crbSignal['recommendation'] === 'reject') {
            $blockers[] = 'CRB leans REJECT ('.$crbSignal['label'].')';
        } elseif ($crbSignal['recommendation'] === 'refer') {
            $signals[] = 'CRB leans REFER — do not auto-approve';
        }

        if ($criticalFlags > 0) {
            $blockers[] = $criticalFlags.' critical review flag'.($criticalFlags === 1 ? '' : 's');
        } elseif ($warningFlags > 0) {
            $signals[] = $warningFlags.' warning flag'.($warningFlags === 1 ? '' : 's');
        }

        $sequence = app(ScreeningSequenceService::class)->snapshot($application);
        $laterUnlocked = (bool) ($sequence['later_unlocked'] ?? false);
        $unlocked = is_array($sequence['unlocked'] ?? null) ? $sequence['unlocked'] : [];
        if ($unlocked !== []) {
            $nextSteps = array_values(array_filter(
                $nextSteps,
                function ($step) use ($unlocked) {
                    $gate = (string) ($step['gate'] ?? '');
                    $desk = match ($gate) {
                        'statements_vs_declared', 'declared', 'income' => 'income',
                        default => $gate,
                    };
                    if ($desk === '' || ($unlocked[$desk] ?? true)) {
                        return true;
                    }

                    return in_array((string) ($step['tone'] ?? ''), ['critical', 'fail'], true);
                }
            ));
        }

        // Prefer Gate 2 (statements vs declared revenue), then critical / open actions.
        usort($nextSteps, function ($a, $b) {
            $rank = ['gate' => 0, 'critical' => 1, 'fail' => 2, 'open' => 3];

            return ($rank[$a['tone'] ?? 'open'] ?? 9) <=> ($rank[$b['tone'] ?? 'open'] ?? 9);
        });

        $incomeGateStep = collect($nextSteps)->firstWhere('gate', 'statements_vs_declared');
        $incomeGateOpen = $incomeGateStep !== null;

        // Keep Gate 2 first even after slicing the list for the UI.
        if ($incomeGateStep !== null) {
            $nextSteps = array_values(array_filter(
                $nextSteps,
                fn ($step) => ($step['gate'] ?? null) !== 'statements_vs_declared'
            ));
            array_unshift($nextSteps, $incomeGateStep);
        }

        $borrower = collect($subjects)->firstWhere('person', 'borrower') ?? ($subjects[0] ?? []);
        $docService = app(ApplicationDocumentRequestService::class);
        $blockingItems = [];
        $application->loadMissing(['documentRequests.subjectCustomer', 'documentRequests.groupMember.customer']);
        $seenBlockers = [];
        foreach ($application->documentRequests as $request) {
            if (! $docService->isOutstanding($request)) {
                continue;
            }
            $label = trim((string) ($request->label ?? 'Requested document'));
            $who = $request->subjectRoleLabel();
            $full = $who ? $label.' ('.$who.')' : $label;
            $seenBlockers[mb_strtolower($full)] = true;
            $kind = $docService->borrowerActionKind($request);
            $href = $docService->screeningReviewUrl($request, $application, collect($review['guarantors'] ?? [])->all());
            $cta = match (true) {
                $request->status === 'uploaded' => 'Review submission',
                $kind === 'income' => 'Review statements',
                $kind === 'collateral' => 'Open collateral',
                in_array($kind, ['identity', 'face'], true) => 'Open identity',
                default => 'Open request',
            };
            $state = match ($request->status) {
                'uploaded' => ' · Waiting for your review',
                'rejected' => ' · Replacement requested',
                default => ' · Waiting for borrower',
            };
            $blockingItems[] = [
                'label' => $full.$state,
                'detail' => $docService->outstandingTimingPhrase($request),
                'href' => $href,
                'cta' => $cta,
                'dedupe_key' => 'request-'.$request->id,
            ];
        }
        foreach (app(LoanApplicationWorkflowService::class)->screeningDocumentBlockers($application) as $blocker) {
            $key = mb_strtolower($blocker);
            if (isset($seenBlockers[$key]) || LoanProductRequirement::nameIsIncomeEvidenceRequirement($blocker)) {
                continue;
            }
            $seenBlockers[$key] = true;
            $dest = $gateService->destination($application, 'documents.required_docs_complete', $borrower);
            $blockingItems[] = [
                'label' => $blocker.' · Missing',
                'detail' => 'Required on this product — still outstanding.',
                'href' => $dest['href'],
                'cta' => 'Open request',
                'dedupe_key' => 'doc-'.$key,
            ];
        }
        $needsAttention = [];
        $seenAttention = collect($blockingItems)->pluck('dedupe_key')->filter()->all();
        foreach ($nextSteps as $step) {
            $dedupe = (string) ($step['dedupe_key'] ?? $step['label'] ?? '');
            if ($dedupe !== '' && in_array($dedupe, $seenAttention, true)) {
                continue;
            }
            $stepLabel = (string) ($step['label'] ?? '');
            if (str_contains(mb_strtolower($stepLabel), 'required document')) {
                continue;
            }
            if (str_contains($dedupe, 'crb_reviewed') && ($crbSignal['recommendation'] ?? '') === 'refer') {
                $stepLabel = 'CRB recommendation is Refer';
            }
            $seenAttention[] = $dedupe;
            $needsAttention[] = [
                'label' => $stepLabel,
                'detail' => $step['detail'] ?? '',
                'href' => $step['href'] ?? '',
                'cta' => $step['cta'] ?? match ($step['tone'] ?? '') {
                    'gate' => 'Review statements',
                    'critical', 'fail' => 'Open check',
                    default => 'Open check',
                },
            ];
        }
        foreach ($anomalies as $anomaly) {
            $title = trim((string) ($anomaly['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $dup = collect($needsAttention)->contains(fn ($row) => str_contains(mb_strtolower($row['label'] ?? ''), mb_strtolower($title)))
                || collect($blockingItems)->contains(fn ($row) => str_contains(mb_strtolower($row['label'] ?? ''), mb_strtolower($title)));
            if ($dup) {
                continue;
            }
            $needsAttention[] = [
                'label' => $title,
                'detail' => (string) ($anomaly['detail'] ?? ''),
                'href' => (string) ($anomaly['href'] ?? $gateService->destination($application, 'credit_file.crb_reviewed', $borrower)['href']),
                'cta' => (string) ($anomaly['cta'] ?? 'Open check'),
            ];
        }

        $docBlockers = app(LoanApplicationWorkflowService::class)->screeningDocumentBlockers($application);
        $ready = $incomplete === [] && $checklistTotal > 0 && $docBlockers === [];
        $decisionRecorded = filled($application->recommended_at) || filled($application->recommendation_type);
        $status = match (true) {
            $decisionRecorded && $ready => 'decision_recorded',
            $blockingItems !== [] || $docBlockers !== [] => 'needs_attention',
            ! $ready => 'review_in_progress',
            default => 'ready_for_decision',
        };
        $statusLabel = match ($status) {
            'decision_recorded' => 'Decision recorded',
            'needs_attention' => 'Needs attention',
            'ready_for_decision' => 'Ready for decision',
            default => 'Review in progress',
        };
        $suggestion = $this->suggest(
            $ready,
            $checklistFailed,
            $criticalFailCount,
            $affordVerdict,
            $crbSignal['recommendation'],
            $criticalFlags,
        );
        $labels = [
            'hold' => 'Hold — finish checklist',
            'approve' => 'Lean Approve',
            'reject' => 'Lean Reject',
            'counter' => 'Lean Counter-offer / Refer',
        ];

        $firstBlock = $blockingItems[0] ?? $needsAttention[0] ?? null;
        $decisionUrl = route('admin.loan-applications.show', [
            'loan_application' => $application,
            'workspace' => 'decision',
        ]).'#review-recommendation';
        $seqNext = $sequence['next_action'] ?? [];
        $primaryHref = $ready && $laterUnlocked
            ? $decisionUrl
            : (string) ($seqNext['href'] ?? $firstBlock['href'] ?? '');
        $primaryCta = match (true) {
            $ready && $laterUnlocked => 'Continue to decision',
            filled($seqNext['cta'] ?? null) => (string) $seqNext['cta'],
            $firstBlock !== null => (string) ($firstBlock['cta'] ?? 'Open check'),
            default => 'Open review checklist',
        };

        $percent = $checklistTotal > 0 ? (int) round(($checklistDone / $checklistTotal) * 100) : 0;
        $autoCompleted = array_values($autoCompleted);
        $autoCompleteCount = count($autoCompleted);
        $humanOpen = max(0, $checklistTotal - $checklistDone);
        $unresolved = array_values(array_merge($blockingItems, $needsAttention));

        return [
            'ready' => $ready,
            'status' => $status,
            'status_label' => $statusLabel,
            'suggestion' => $suggestion,
            'suggestion_label' => $labels[$suggestion] ?? strtoupper($suggestion),
            'headline' => $statusLabel,
            'detail' => $this->detail($ready, $suggestion, $checklistDone, $checklistTotal, $criticalFailCount),
            'tone' => match ($status) {
                'ready_for_decision', 'decision_recorded' => $suggestion === 'reject' ? 'bad' : 'good',
                'needs_attention' => 'warn',
                default => 'neutral',
            },
            'blockers' => array_values($blockers),
            'signals' => array_values($signals),
            'next_steps' => array_values($nextSteps),
            'critical_fails' => array_values(array_slice($criticalFails, 0, 8)),
            'checklist_done' => $checklistDone,
            'checklist_total' => $checklistTotal,
            'checklist_failed' => $checklistFailed,
            'checklist_percent' => $percent,
            'auto_complete_count' => $autoCompleteCount,
            'auto_completed' => $autoCompleted,
            'needs_attention' => $needsAttention,
            'blocking_items' => $blockingItems,
            'unresolved' => $unresolved,
            'submissions' => $this->submissions($application, $review, $docService),
            'overview_snapshot' => $this->overviewSnapshot($application, $review, $groupReview, $affordVerdict, $crbSignal),
            'gate_chips' => $this->gateChips($gateAgg),
            'subjects_incomplete' => count($incomplete),
            'subjects_total' => count($subjects),
            'income_gate_open' => $incomeGateOpen,
            'income_gate_href' => $incomeGateOpen ? (string) ($incomeGateStep['href'] ?? '') : null,
            'primary_href' => $primaryHref,
            'primary_cta' => $primaryCta,
            'primary_block_cta' => $firstBlock['cta'] ?? 'Open check',
            'human_open' => $humanOpen,
            'attention_count' => count($unresolved),
            'sequence' => $sequence,
            'next_action' => $seqNext,
            'member_summaries' => $memberSummaries,
            'decision_status' => $this->decisionStatus(
                $sequence,
                $ready,
                $criticalFailCount,
                $criticalFails,
                $laterUnlocked,
                $checklistFailed,
            ),
            'na_note' => 'N/A counts as reviewed and does not Fail the file — use it when the check truly does not apply (for example collateral on a clean group loan). It still moves the checklist forward.',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function autoCheckDetail(array $item, string $subjectLabel): string
    {
        $rows = collect($item['evidence']['rows'] ?? [])
            ->map(fn ($row) => trim((string) ($row['label'] ?? '').': '.(string) ($row['value'] ?? '')))
            ->filter()
            ->take(2)
            ->implode(' · ');

        return trim($subjectLabel.($rows !== '' ? ' — '.$rows : ''));
    }

    /**
     * @param  array<string, array{decided: int, total: int, failed: int}>  $gateAgg
     * @return list<array{key: string, chip: string, complete: bool}>
     */
    private function gateChips(array $gateAgg): array
    {
        $chips = [];
        foreach (ScreeningChecklistGateService::SHORT as $key => $short) {
            $row = $gateAgg[$key] ?? null;
            if ($row === null || (int) ($row['total'] ?? 0) < 1) {
                continue;
            }
            $remaining = max(0, (int) $row['total'] - (int) $row['decided']);
            $complete = $remaining === 0 && (int) ($row['failed'] ?? 0) === 0;
            $status = match (true) {
                (int) ($row['failed'] ?? 0) > 0 => 'Attention',
                $complete => 'Complete',
                $remaining > 0 => $remaining.' remaining',
                default => 'Waiting',
            };
            $chips[] = [
                'key' => $key,
                'chip' => match ($status) {
                    'Complete' => $short.' ✓',
                    'Attention' => $short.' · Attention',
                    default => $short.' · '.$status,
                },
                'complete' => $complete,
            ];
        }

        return $chips;
    }

    /**
     * @param  array<string, mixed>  $review
     * @return list<array{label: string, detail: string, status: string, href: string, cta: string}>
     */
    private function submissions(LoanApplication $application, array $review, ApplicationDocumentRequestService $docService): array
    {
        $rows = [];
        $application->loadMissing(['documentRequests.subjectCustomer', 'documentRequests.groupMember.customer']);
        foreach ($application->documentRequests as $request) {
            $kind = $docService->borrowerActionKind($request);
            $status = (string) $request->status;
            $rows[] = [
                'label' => trim((string) ($request->label ?? 'Requested document')),
                'detail' => trim($request->subjectRoleLabel().' · '.$docService->screeningKindLabel($request)),
                'status' => $status,
                'href' => $docService->screeningReviewUrl($request, $application, collect($review['guarantors'] ?? [])->all()),
                'cta' => match ($kind) {
                    'income' => 'Review statements',
                    'collateral' => 'Open collateral',
                    'identity', 'face' => 'Open identity',
                    default => 'Open request',
                },
            ];
        }

        $collateral = (array) ($review['collateral'] ?? $review['pledged_assets'] ?? []);
        if ($collateral !== []) {
            $rows[] = [
                'label' => 'Pledged collateral',
                'detail' => is_countable($collateral) ? count($collateral).' asset(s) on this file' : 'On file',
                'status' => 'uploaded',
                'href' => route('admin.loan-applications.show', [
                    'loan_application' => $application,
                    'workspace' => 'checklist',
                    'gate' => 'collateral',
                ]).'#review-desk',
                'cta' => 'Open collateral',
            ];
        }

        $guarantors = collect($review['guarantors'] ?? []);
        if ($guarantors->isNotEmpty()) {
            $first = $guarantors->first();
            $rows[] = [
                'label' => 'Guarantor · '.trim((string) ($first['name'] ?? 'On file')),
                'detail' => $guarantors->count().' guarantor'.($guarantors->count() === 1 ? '' : 's'),
                'status' => (string) ($first['status'] ?? 'uploaded'),
                'href' => route('admin.loan-applications.show', [
                    'loan_application' => $application,
                    'workspace' => 'profiles',
                    'person' => 'guarantor',
                    'g' => $first['link_id'] ?? null,
                ]),
                'cta' => 'Open guarantor file',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>|null  $groupReview
     * @param  array{recommendation: string, label: string}  $crbSignal
     * @return list<array{label: string, value: string}>
     */
    private function overviewSnapshot(
        LoanApplication $application,
        array $review,
        ?array $groupReview,
        string $affordVerdict,
        array $crbSignal,
    ): array {
        $risk = (array) ($review['risk'] ?? []);
        $gSug = (array) ($review['guarantor_suggestion'] ?? []);
        $gRec = strtolower((string) ($gSug['recommendation'] ?? ''));
        $isGroup = collect($groupReview['members'] ?? [])->isNotEmpty();

        return [
            [
                'label' => 'Facility',
                'value' => format_money((float) $application->requested_amount)
                    .' · '.(int) $application->requested_tenure_months.' months',
            ],
            [
                'label' => 'CRB',
                'value' => strtoupper($crbSignal['recommendation'] ?: '—')
                    .' · '.($crbSignal['label'] ?? '—'),
            ],
            [
                'label' => 'Risk',
                'value' => trim((string) ($risk['label'] ?? '—'))
                    .' · '.($risk['score'] ?? '—').'/100',
            ],
            [
                'label' => $isGroup ? 'Roster' : 'Guarantor',
                'value' => $isGroup
                    ? ((int) ($groupReview['member_count'] ?? 0).' members')
                    : ($gRec !== '' ? strtoupper($gRec) : (string) ($gSug['label'] ?? '—')),
            ],
            [
                'label' => 'Affordability',
                'value' => $affordVerdict !== '' ? ucfirst($affordVerdict) : 'Not calculated',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>|null  $groupReview
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, mixed>
     */
    private function memberSummaryCard(
        LoanApplication $application,
        array $subject,
        ?array $groupReview,
        int $open,
        int $failed,
        array $issues,
    ): array {
        $person = (string) ($subject['person'] ?? 'borrower');
        $name = trim((string) ($subject['sublabel'] ?? $subject['label'] ?? 'Member'));
        $first = explode(' ', $name)[0] ?: $name;
        $cid = (int) ($subject['customer_id'] ?? 0);
        $memberRow = collect($groupReview['members'] ?? [])->first(function ($row) use ($person, $subject, $cid) {
            if ($person === 'member' && (int) ($row['id'] ?? 0) === (int) ($subject['m'] ?? 0)) {
                return true;
            }
            if ($person === 'borrower' && ($row['role'] ?? '') === 'leader') {
                return true;
            }

            return $cid > 0 && (int) ($row['customer_id'] ?? 0) === $cid;
        });
        $gate1 = strtolower((string) ($memberRow['gate_1'] ?? ''));
        $gate2 = strtolower((string) ($memberRow['gate_2'] ?? ''));
        $crb = strtolower((string) ($memberRow['crb_recommendation'] ?? ''));
        $issue = $issues[0] ?? null;
        $needsAttention = $open > 0 || $failed > 0;
        $href = (string) ($issue['href'] ?? route('admin.loan-applications.show', array_filter([
            'loan_application' => $application,
            'workspace' => 'checklist',
            'review_person' => $person,
            'review_g' => $subject['g'] ?? null,
            'review_m' => $subject['m'] ?? null,
            'gate' => $issue['gate'] ?? 'identity',
        ])).'#review-desk');

        $chips = [];
        if ($gate1 !== '') {
            $chips[] = 'Gate 1 '.($gate1 === 'pass' ? '✓' : ($gate1 === 'fail' ? '✗' : $gate1));
        }
        if ($gate2 !== '') {
            $chips[] = 'Gate 2 '.($gate2 === 'pass' ? '✓' : ($gate2 === 'fail' ? '✗' : $gate2));
        }
        if ($crb !== '') {
            $chips[] = 'CRB '.ucfirst($crb);
        }

        return [
            'key' => (string) ($subject['key'] ?? $person),
            'person' => $person,
            'name' => $first,
            'full_name' => $name,
            'role' => (string) ($subject['label'] ?? 'Member'),
            'status' => $needsAttention ? 'Needs attention' : 'Eligible',
            'needs_attention' => $needsAttention,
            'chips' => $chips,
            'issue' => $issue['label'] ?? null,
            'issue_detail' => $issue['detail'] ?? null,
            'href' => $href,
            'cta' => $issue['cta'] ?? 'Open member',
        ];
    }

    /**
     * @param  array<string, mixed>  $sequence
     * @param  list<string>  $criticalFails
     * @return array{state: string, headline: string, detail: string, countdown: ?string}
     */
    private function decisionStatus(
        array $sequence,
        bool $ready,
        int $criticalFailCount,
        array $criticalFails,
        bool $laterUnlocked,
        int $checklistFailed,
    ): array {
        $pending = (bool) ($sequence['pending_rejection'] ?? false);
        $countdown = $sequence['remaining_label'] ?? null;
        $parkGate = (string) ($sequence['park_gate'] ?? '');
        $failLabel = $parkGate === 'verified'
            ? 'Gate 2 — Verified affordability failed'
            : 'Gate 1 — Initial affordability failed';

        if ($pending) {
            return [
                'state' => 'pending_rejection',
                'headline' => '1 hard failure',
                'detail' => $failLabel,
                'countdown' => $countdown ? 'Pending automatic rejection · '.$countdown : 'Pending automatic rejection',
            ];
        }

        if ($criticalFailCount > 0) {
            return [
                'state' => 'hard_failure',
                'headline' => $criticalFailCount === 1 ? '1 hard failure' : $criticalFailCount.' hard failures',
                'detail' => (string) ($criticalFails[0] ?? 'Critical checklist Fail'),
                'countdown' => $checklistFailed > 0 ? 'Recorded on Screening summary — confirm rejection from Decision' : null,
            ];
        }

        if ($ready && $laterUnlocked) {
            return [
                'state' => 'ready',
                'headline' => 'Ready for decision',
                'detail' => 'No decision blockers',
                'countdown' => null,
            ];
        }

        return [
            'state' => 'clear',
            'headline' => 'No decision blockers',
            'detail' => 'Keep working the remaining screening questions.',
            'countdown' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function checklistHref(
        LoanApplication $application,
        array $subject,
        string $phase,
        ?string $openGroup = null,
        ?string $openItem = null,
        ?string $capacityTab = null,
        ?string $securityTab = null,
        string $fragment = 'review-desk',
    ): string {
        $query = array_filter([
            'loan_application' => $application,
            'workspace' => 'checklist',
            'review_person' => $subject['person'] ?? 'borrower',
            'review_g' => $subject['g'] ?? null,
            'review_m' => $subject['m'] ?? null,
            'desk_phase' => $phase,
            'open_group' => $openGroup,
            'open_item' => $openItem,
            'capacity_tab' => $capacityTab,
            'security_tab' => $securityTab ?: ($phase === 'security' && ! $openGroup ? 'wrapup' : null),
        ], fn ($v) => $v !== null && $v !== '');

        return route('admin.loan-applications.show', $query).'#'.$fragment;
    }

    /**
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>|null  $groupReview
     * @return array{recommendation: string, label: string}
     */
    private function crbSignal(array $review, ?array $groupReview): array
    {
        $members = collect($groupReview['members'] ?? []);
        if ($members->isNotEmpty()) {
            $worst = 'approve';
            foreach ($members as $member) {
                $score = isset($member['crb_score']) && is_numeric($member['crb_score'])
                    ? (int) $member['crb_score']
                    : null;
                $band = $this->crb->scoreBandFeedback($score);
                $worst = $this->worseCrb($worst, $band['recommendation']);
            }

            return [
                'recommendation' => $worst,
                'label' => $members->count().' members',
            ];
        }

        $crb = (array) ($review['crb'] ?? []);
        $score = isset($crb['score']) && is_numeric($crb['score']) ? (int) $crb['score'] : null;
        $band = $this->crb->scoreBandFeedback($score);
        $rec = strtolower((string) ($crb['recommendation'] ?? $band['recommendation']));
        if (! in_array($rec, ['approve', 'refer', 'reject'], true)) {
            $rec = $band['recommendation'];
        }

        return [
            'recommendation' => $rec,
            'label' => $band['label'],
        ];
    }

    private function worseCrb(string $current, string $next): string
    {
        $rank = ['approve' => 1, 'refer' => 2, 'reject' => 3];

        return ($rank[$next] ?? 2) > ($rank[$current] ?? 2) ? $next : $current;
    }

    private function suggest(
        bool $ready,
        int $checklistFailed,
        int $criticalFailCount,
        string $affordVerdict,
        string $crbRec,
        int $criticalFlags,
    ): string {
        if (! $ready) {
            return 'hold';
        }

        // High-risk checklist fails are a no-brainer lean Reject once the file is complete.
        if ($criticalFailCount > 0 || $affordVerdict === 'fail' || $crbRec === 'reject' || $criticalFlags > 0) {
            return 'reject';
        }

        if ($checklistFailed > 0 || $affordVerdict === 'warn' || $crbRec === 'refer') {
            return 'counter';
        }

        if ($crbRec === 'approve' && in_array($affordVerdict, ['pass', ''], true)) {
            return 'approve';
        }

        return 'counter';
    }

    private function headline(bool $ready, string $suggestion): string
    {
        if (! $ready) {
            return 'Not ready to decide yet';
        }

        return match ($suggestion) {
            'approve' => 'Ready — file leans Approve',
            'reject' => 'Ready — file leans Reject',
            'counter' => 'Ready — lean Counter-offer / careful Refer',
            default => 'Ready for Decision',
        };
    }

    private function detail(bool $ready, string $suggestion, int $done, int $total, int $criticalFailCount): string
    {
        $progress = $total > 0 ? "{$done}/{$total} checklist items reviewed" : 'No checklist items yet';

        if (! $ready) {
            return $progress.'. Expand “Where to go next” below, finish those items, then open Decision. Decision stays open, but lean guidance waits until every subject is reviewed.';
        }

        if ($criticalFailCount > 0) {
            return $progress.". {$criticalFailCount} high-risk Fail(s) — lean Reject unless you deliberately override with written reasons.";
        }

        return match ($suggestion) {
            'approve' => $progress.'. Checklist clear, capacity and CRB support approval — confirm on Decision.',
            'reject' => $progress.'. Fail signals are strong enough to lean reject — record reasons on Decision.',
            'counter' => $progress.'. Mixed or moderate signals — prefer counter-offer or a cautious recommendation.',
            default => $progress.'. Open Decision to record your recommendation.',
        };
    }
}
