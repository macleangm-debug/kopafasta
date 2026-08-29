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

        $checklistDone = 0;
        $checklistTotal = 0;
        $checklistFailed = 0;
        $incomplete = [];
        $failedSubjects = [];
        $nextSteps = [];
        $criticalFails = [];
        $criticalFailCount = 0;
        $autoCompleted = [];

        foreach ($subjects as $subject) {
            $checklistDone += (int) ($subject['done'] ?? 0);
            $checklistTotal += (int) ($subject['total'] ?? 0);
            $checklistFailed += (int) ($subject['failed'] ?? 0);
            if (! ($subject['complete'] ?? false)) {
                $incomplete[] = trim(($subject['label'] ?? 'Subject').' · '.($subject['sublabel'] ?? ''));
            }
            if ((int) ($subject['failed'] ?? 0) > 0) {
                $failedSubjects[] = trim(($subject['label'] ?? 'Subject').' · '.($subject['sublabel'] ?? ''));
            }

            $desk = $this->checklist->deskViewModel(
                $application,
                $review,
                $groupReview,
                $actor,
                (string) ($subject['person'] ?? 'borrower'),
                isset($subject['g']) ? (int) $subject['g'] : null,
                isset($subject['m']) ? (int) $subject['m'] : null,
            );

            foreach ($desk['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    $verdict = $item['verdict'] ?? null;
                    $risk = (string) ($item['risk'] ?? 'normal');
                    $gate = (string) ($item['gate'] ?? '');
                    $isIncomeGate = $gate === 'statements_vs_declared'
                        || ($item['key'] ?? '') === 'activity_income.income_evidence';
                    $subjectLabel = trim(($subject['label'] ?? 'Subject').' · '.($subject['sublabel'] ?? ''));
                    $dest = $item['destination'] ?? app(ScreeningChecklistGateService::class)->destination(
                        $application,
                        (string) ($item['key'] ?? ''),
                        $subject,
                    );
                    $href = (string) ($dest['href'] ?? '');
                    $cta = (string) ($dest['cta'] ?? 'Open check');
                    $autoSource = ! empty($item['system_checked'])
                        || ! empty($item['catalog_system'])
                        || ! empty($item['documents_checked'])
                        || in_array((string) ($item['by_name'] ?? ''), ['System', 'Documents'], true);

                    if (in_array($verdict, ['pass', 'na'], true) && $autoSource) {
                        $autoCompleted[] = [
                            'label' => ($item['label'] ?? 'Check').($verdict === 'na' ? ' (N/A)' : ''),
                            'detail' => $subjectLabel,
                        ];
                    }

                    if ($verdict === null && $autoSource && empty($item['captures_statement'])) {
                        continue;
                    }

                    if ($verdict === null) {
                        $hasEvidence = ! empty($item['evidence']['photos'])
                            || ! empty($item['evidence']['rows'])
                            || ! empty($item['evidence']['compare']);
                        $nextSteps[] = [
                            'label' => $isIncomeGate
                                ? 'Gate 2 · Statement totals'
                                : (($hasEvidence ? 'Needs review · ' : 'Still open · ').($item['label'] ?? 'Checklist item')),
                            'detail' => $isIncomeGate
                                ? $subjectLabel.' · Enter statement totals and Save. Then record whether activity supports the stated income.'
                                : ($subjectLabel.' · '.($group['phase_label'] ?? $group['label'] ?? 'Checklist')
                                    .($hasEvidence ? ' — evidence is ready; record Pass or Concern and Save' : '')),
                            'href' => $href,
                            'cta' => $cta,
                            'tone' => $isIncomeGate ? 'gate' : ($risk === 'critical' ? 'critical' : 'open'),
                            'gate' => $isIncomeGate ? 'statements_vs_declared' : ($dest['gate'] ?? null),
                            'dedupe_key' => (string) ($item['key'] ?? $item['label'] ?? ''),
                        ];
                    } elseif ($verdict === 'fail') {
                        if ($risk === 'critical') {
                            $criticalFailCount++;
                            $criticalFails[] = ($item['label'] ?? 'Check').' ('.$subjectLabel.')';
                        }
                        $nextSteps[] = [
                            'label' => ($isIncomeGate ? 'Gate 2 concern · ' : 'Concern · ').($item['label'] ?? 'Checklist item'),
                            'detail' => $subjectLabel.(! empty($item['fail_reason_label']) ? ' — '.$item['fail_reason_label'] : ' — add a reason, then Save'),
                            'href' => $href,
                            'cta' => $cta,
                            'tone' => $isIncomeGate ? 'gate' : ($risk === 'critical' ? 'critical' : 'fail'),
                            'gate' => $isIncomeGate ? 'statements_vs_declared' : ($dest['gate'] ?? null),
                            'dedupe_key' => (string) ($item['key'] ?? $item['label'] ?? ''),
                        ];
                    }
                }
            }
        }

        // Point to documents when product docs are behind.
        $required = (int) ($review['required_docs'] ?? 0);
        $satisfied = (int) ($review['satisfied_docs'] ?? 0);
        if ($required > 0 && $satisfied < $required && count($nextSteps) < 12) {
            $borrower = collect($subjects)->firstWhere('person', 'borrower') ?? ($subjects[0] ?? null);
            if ($borrower) {
                $nextSteps[] = [
                    'label' => ($required - $satisfied).' required document(s) not verified',
                    'detail' => 'Capacity → Documents',
                    'href' => $this->checklistHref($application, $borrower, 'capacity', null, null, 'documents'),
                    'tone' => 'open',
                ];
            }
        }

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
        $gateService = app(ScreeningChecklistGateService::class);
        $blockingItems = [];
        $application->loadMissing(['documentRequests.subjectCustomer', 'documentRequests.groupMember.customer']);
        $seenBlockers = [];
        foreach ($application->documentRequests as $request) {
            if (! $request->needsBorrowerAction()) {
                continue;
            }
            $label = trim((string) ($request->label ?? 'Requested document'));
            $who = $request->subjectRoleLabel();
            $full = $who ? $label.' ('.$who.')' : $label;
            $seenBlockers[mb_strtolower($full)] = true;
            $kind = $docService->borrowerActionKind($request);
            $href = $docService->screeningReviewUrl($request, $application, collect($review['guarantors'] ?? [])->all());
            $cta = match ($kind) {
                'income' => 'Review statements',
                'collateral' => 'Open collateral',
                'identity', 'face' => 'Open identity',
                default => 'Open request',
            };
            $wait = $docService->outstandingTimingPhrase($request);
            $blockingItems[] = [
                'label' => $full,
                'detail' => trim($docService->waitingOnLabel($request).($wait ? ' · '.$wait : '')).' — this blocks Committee.',
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
                'label' => $blocker,
                'detail' => 'Outstanding — this blocks Committee.',
                'href' => $dest['href'],
                'cta' => 'Open request',
                'dedupe_key' => 'doc-'.$key,
            ];
        }
        $needsAttention = [];
        $seenAttention = collect($blockingItems)->pluck('dedupe_key')->filter()->all();
        foreach (array_slice($nextSteps, 0, 10) as $step) {
            $dedupe = (string) ($step['dedupe_key'] ?? $step['label'] ?? '');
            if ($dedupe !== '' && in_array($dedupe, $seenAttention, true)) {
                continue;
            }
            $stepLabel = (string) ($step['label'] ?? '');
            if (str_contains(mb_strtolower($stepLabel), 'required document') && $blockingItems !== []) {
                continue;
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
        $primaryHref = $ready ? $decisionUrl : (string) ($firstBlock['href'] ?? '');
        $primaryCta = match (true) {
            $ready => 'Continue to decision',
            $blockingItems !== [] => (count($blockingItems) === 1 ? '1 item before decision' : count($blockingItems).' items before decision'),
            default => 'Open missing item',
        };

        $percent = $checklistTotal > 0 ? (int) round(($checklistDone / $checklistTotal) * 100) : 0;
        $autoCompleted = array_values(array_slice($autoCompleted, 0, 12));
        $autoCompleteCount = count($autoCompleted);
        $humanOpen = max(0, $checklistTotal - $checklistDone - $autoCompleteCount);

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
            'next_steps' => array_values(array_slice($nextSteps, 0, 10)),
            'critical_fails' => array_values(array_slice($criticalFails, 0, 8)),
            'checklist_done' => $checklistDone,
            'checklist_total' => $checklistTotal,
            'checklist_failed' => $checklistFailed,
            'checklist_percent' => $percent,
            'auto_complete_count' => $autoCompleteCount,
            'auto_completed' => $autoCompleted,
            'needs_attention' => $needsAttention,
            'blocking_items' => $blockingItems,
            'subjects_incomplete' => count($incomplete),
            'subjects_total' => count($subjects),
            'income_gate_open' => $incomeGateOpen,
            'income_gate_href' => $incomeGateOpen ? (string) ($incomeGateStep['href'] ?? '') : null,
            'primary_href' => $primaryHref,
            'primary_cta' => $primaryCta,
            'primary_block_cta' => $firstBlock['cta'] ?? 'Open missing item',
            'human_open' => $humanOpen,
            'na_note' => 'N/A counts as reviewed and does not Fail the file — use it when the check truly does not apply (for example collateral on a clean group loan). It still moves the checklist forward.',
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
            $labels = [];
            foreach ($members as $member) {
                $score = isset($member['crb_score']) && is_numeric($member['crb_score'])
                    ? (int) $member['crb_score']
                    : null;
                $band = $this->crb->scoreBandFeedback($score);
                $labels[] = ($member['name'] ?? 'Member').': '.$band['label'];
                $worst = $this->worseCrb($worst, $band['recommendation']);
            }

            return [
                'recommendation' => $worst,
                'label' => $labels !== [] ? 'group ('.implode('; ', array_slice($labels, 0, 3)).')' : 'group',
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
