<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Canonical next action for Guided Screening.
 * Reads existing sequence + checklist + requests. Does not invent credit rules.
 */
class ScreeningNextActionService
{
    public const BUCKET_DO_NOW = 'do_now';

    public const BUCKET_WAITING = 'waiting';

    public const BUCKET_COMPLETED = 'completed';

    public function __construct(
        private readonly ScreeningChecklistService $checklist,
        private readonly ScreeningChecklistGateService $gates,
        private readonly ScreeningSequenceService $sequence,
        private readonly ApplicationDocumentRequestService $documents,
        private readonly CollateralSecureService $collateralSecure,
        private readonly CapacityAutoRejectService $autoReject,
    ) {}

    /**
     * @param  array{after_item?: ?string, after_person?: ?string, after_m?: ?int, after_g?: ?int}  $cursor
     * @return array<string, mixed>
     */
    public function forApplication(LoanApplication $application, ?User $actor = null, array $cursor = []): array
    {
        $actor = $actor ?? auth()->user();
        $application->loadMissing(['customer', 'product', 'loanGroup.members.customer', 'documentRequests', 'customerGuarantors.invitation']);

        $review = app(LoanApplicationReviewService::class)->dossier($application);
        try {
            $groupReview = app(GroupLoanReviewService::class)->dossier($application) ?? [];
        } catch (\Throwable $e) {
            report($e);
            $groupReview = [];
        }
        if (! is_array($groupReview)) {
            $groupReview = [];
        }

        $subjects = $this->checklist->deskSubjects($application, $review, $groupReview, $actor);
        $progress = $this->checklistProgress($subjects);
        $leaderDesk = $this->checklist->deskViewModel($application, $review, $groupReview, $actor, 'borrower');
        $leaderGates = $this->gates->regroup($leaderDesk['groups'] ?? [], $application);
        $snapshot = $this->sequence->snapshot($application, $leaderGates);
        $waiting = $this->waitingState($application, $snapshot, $groupReview);
        $clarification = $this->unresolvedClarification($application);
        $resolution = is_array($snapshot['policy']['resolution'] ?? null) ? $snapshot['policy']['resolution'] : null;
        $needsResolutionUi = is_array($resolution)
            && ($resolution['blocking'] ?? false)
            && $waiting === null
            && ! ($snapshot['pending_rejection'] ?? false);

        if ($waiting === null
            && ($cursor['at_item'] ?? '') === ''
            && ($cursor['after_item'] ?? '') === '') {
            $cursor = $this->pinUploadedRequest($application, $cursor);
        }

        $step = match (true) {
            $clarification !== null => $this->clarificationStep($clarification),
            $waiting !== null => $this->waitingStep($waiting, $snapshot),
            $needsResolutionUi => $this->resolutionStep($application, $snapshot, $groupReview),
            default => $this->firstActionableStep($application, $actor, $review, $groupReview, $subjects, $snapshot, $cursor),
        };
        $unresolved = $step;
        if (($cursor['at_item'] ?? '') !== '' && ! in_array($step['type'] ?? '', ['waiting', 'clarification', 'resolution', 'return_to_committee'], true)) {
            $unresolved = $this->firstActionableStep($application, $actor, $review, $groupReview, $subjects, $snapshot, array_diff_key($cursor, array_flip(['at_item', 'at_person', 'at_m', 'at_g'])));
        }
        if (empty($cursor['back']) && ! in_array($step['type'] ?? '', ['waiting', 'decision'], true)) {
            $this->pushWalk($application, $step);
        }

        $stage = (string) $application->current_stage;
        $started = filled(data_get($application->screening_payload, 'guided.started_at'));
        $screeningOpen = in_array($stage, ['submitted', 'screening', 'credit_appraisal'], true);
        $stepIsDecision = ($step['type'] ?? '') === 'decision';
        $stillOpen = in_array($step['type'] ?? '', ['human', 'request', 'attention', 'waiting', 'resolution', 'clarification', 'gate_1', 'gate_2_passed', 'gate_3_passed', 'gate_complete', 'gate_remaining', 'collateral_secure'], true);
        $bucket = match (true) {
            ! $screeningOpen || ($stepIsDecision && ! $stillOpen) => self::BUCKET_COMPLETED,
            $waiting !== null => self::BUCKET_WAITING,
            default => self::BUCKET_DO_NOW,
        };

        $ctaKind = match (true) {
            $bucket === self::BUCKET_COMPLETED && $stepIsDecision => 'decision',
            $waiting !== null => 'waiting',
            $started => 'continue',
            default => 'start',
        };
        $cta = match ($ctaKind) {
            'decision' => 'Continue to Decision',
            'waiting' => (string) ($waiting['label'] ?? 'Waiting'),
            'continue' => 'Continue Reviewing',
            default => in_array($stage, ['screening', 'credit_appraisal'], true)
                ? 'Continue Reviewing'
                : 'Start Reviewing',
        };

        $resume = $this->resumeFromStep($unresolved);
        $this->persistQuiet($application, $resume, $bucket, $waiting);

        $deskHref = route('admin.loan-applications.show', [
            'loan_application' => $application,
            'workspace' => 'overview',
        ]).'#credit-workspace';
        $reviewHref = route('admin.loan-applications.guided-screening', array_filter([
            'loan_application' => $application,
            'at_item' => $step['item_key'] ?? null,
            'at_person' => $step['participant']['person'] ?? null,
            'at_m' => $step['participant']['m'] ?? null,
            'at_g' => $step['participant']['g'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
        $decisionHref = route('admin.loan-applications.show', [
            'loan_application' => $application,
            'workspace' => 'decision',
        ]).'#review-recommendation';
        $href = match ($ctaKind) {
            'decision' => $decisionHref,
            default => $deskHref,
        };

        $blockers = $this->unresolvedCount($subjects, $application, $actor, $review, $groupReview);
        $gateProgress = $this->gateProgressForStep($step, $subjects, $application, $actor, $review, $groupReview, $snapshot);

        return [
            'cta' => $cta,
            'cta_kind' => $ctaKind,
            'resumable' => $ctaKind === 'continue' || $ctaKind === 'start',
            'href' => $href,
            'desk_href' => $deskHref,
            'review_href' => $reviewHref,
            'prev_href' => $this->previousWalkHref($application),
            'checklist_href' => guided_evidence_url(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'gate' => $resume['gate'] ?? 'income',
                'review_person' => $resume['person'] ?? 'borrower',
                'review_m' => $resume['m'] ?? null,
                'review_g' => $resume['g'] ?? null,
                'open_item' => $resume['item'] ?? null,
            ]).'#review-desk', 'guided', [
                'open_item' => $resume['item'] ?? null,
                'review_person' => $resume['person'] ?? null,
                'review_m' => $resume['m'] ?? null,
                'review_g' => $resume['g'] ?? null,
            ]),
            'bucket' => $bucket,
            'waiting' => $waiting,
            'started' => $started,
            'last_activity_at' => data_get($application->screening_payload, 'guided.last_activity_at'),
            'gate' => $resume['gate'] ?? ($step['gate'] ?? 'income'),
            'current_gate' => $resume['gate'] ?? ($step['gate'] ?? 'income'),
            'gate_index' => (int) ($step['gate_index'] ?? 1),
            'gate_total' => 6,
            'gate_label' => (string) ($step['gate_label'] ?? ''),
            'current_gate_label' => trim(
                'Gate '.((int) ($step['gate_index'] ?? 1))
                .(! empty($step['gate_label']) ? ' · '.$step['gate_label'] : '')
            ),
            'percent' => $progress['percent'],
            'checks_done' => $progress['done'],
            'checks_total' => $progress['total'],
            'remaining' => $progress['remaining'],
            'remaining_blockers' => $blockers,
            'gate_progress' => $gateProgress,
            'product_name' => $application->product?->name,
            'participant' => $step['participant'] ?? null,
            'step' => $step,
            'what_happens_next' => $this->whatHappensNext($step, $waiting, $ctaKind, $blockers),
            'recommended' => $step['recommended'] ?? null,
            'sequence' => $snapshot,
            'gate2_summary' => $this->gate2Summary($application, $actor, $review, $groupReview, $subjects),
            'gate3_summary' => $this->gate3Summary($application, $actor, $review, $groupReview, $subjects),
            'subjects' => (function () use ($application, $subjects, $actor, $review, $groupReview) {
                $guarantorTotal = collect($subjects)->where('person', 'guarantor')->count();
                $guarantorIndex = 0;
                $mapped = [];
                foreach ($subjects as $s) {
                    $name = filled($s['sublabel'] ?? null)
                        ? (string) $s['sublabel']
                        : (string) ($s['label'] ?? 'Participant');
                    if (($s['person'] ?? '') === 'guarantor') {
                        $guarantorIndex++;
                        if ($guarantorTotal > 1 && ! filled($s['sublabel'] ?? null)) {
                            $name = 'Guarantor '.$guarantorIndex;
                        }
                    }
                    $gate2 = $this->personGate2Status(
                        $application,
                        $actor,
                        $review,
                        $groupReview,
                        $s,
                    );
                    $gate3 = $this->personGate3Status(
                        $application,
                        $actor,
                        $review,
                        $groupReview,
                        $s,
                    );
                    $mapped[] = [
                        'label' => $name,
                        'role' => $s['label'] ?? null,
                        'person' => $s['person'] ?? null,
                        'm' => $s['m'] ?? null,
                        'g' => $s['g'] ?? null,
                        'customer_id' => $s['customer_id'] ?? null,
                        'complete' => (bool) ($s['complete'] ?? false),
                        'failed' => (int) ($s['failed'] ?? 0),
                        'done' => (int) ($s['done'] ?? 0),
                        'total' => (int) ($s['total'] ?? 0),
                        'gate2' => $gate2,
                        'gate3' => $gate3,
                        'href' => route('admin.loan-applications.guided-screening', array_filter([
                            'loan_application' => $application,
                            'focus_person' => $s['person'] ?? null,
                            'focus_m' => $s['m'] ?? null,
                            'focus_g' => $s['g'] ?? null,
                        ])),
                    ];
                }

                return $mapped;
            })(),
        ];
    }

    /**
     * @param  array<string, mixed>  $subject
     * @return array{chip: string, label: string}
     */
    private function personGate2Status(
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
        array $subject,
    ): array {
        $person = (string) ($subject['person'] ?? 'borrower');
        $m = isset($subject['m']) ? (int) $subject['m'] : null;
        $g = isset($subject['g']) ? (int) $subject['g'] : null;
        $desk = $this->checklist->deskViewModel($application, $review, $groupReview, $actor, $person, $g, $m);
        $gates = $this->gates->regroup($desk['groups'] ?? [], $application);
        $income = $gates['income'] ?? null;
        $total = (int) ($income['total'] ?? 0);
        $decided = (int) ($income['decided'] ?? 0);
        $failed = (int) ($income['failed'] ?? 0);
        $capacityFail = false;
        $checks = [
            'capacity' => ['label' => 'Capacity', 'chip' => 'WAITING'],
            'activity' => ['label' => 'Activity evidence', 'chip' => 'WAITING'],
            'patterns' => ['label' => 'Statement patterns', 'chip' => 'WAITING'],
        ];
        $openOnDesk = 0;
        foreach ($income['groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $key = (string) ($item['key'] ?? '');
                if ($this->itemNeedsAction($item)) {
                    $openOnDesk++;
                }
                if (! empty($item['captures_statement']) && is_array($item['capacity'] ?? null)) {
                    $capacity = $item['capacity'];
                    if (($capacity['income_basis'] ?? '') === 'statement') {
                        $checks['capacity']['chip'] = ! empty($capacity['capacity_pass']) ? 'PASSED' : 'FAILED';
                        if (empty($capacity['capacity_pass'])) {
                            $capacityFail = true;
                        }
                    } elseif ((float) ($item['statement_monthly'] ?? 0) <= 0) {
                        $checks['capacity']['chip'] = 'WAITING';
                    }
                }
                if ($key === 'activity_income.activity_plausible') {
                    $checks['activity']['chip'] = match ($item['verdict'] ?? null) {
                        'pass', 'na' => 'PASSED',
                        'fail' => 'FAILED',
                        default => 'WAITING',
                    };
                }
                if ($key === 'activity_income.bank_or_mobile_money') {
                    $checks['patterns']['chip'] = match ($item['verdict'] ?? null) {
                        'pass', 'na' => 'PASSED',
                        'fail' => 'FAILED',
                        default => 'WAITING',
                    };
                }
                if ($key === 'guarantor_wrap.capacity_confirmed') {
                    $checks['capacity_confirmed'] = [
                        'label' => 'Guarantor capacity confirmed',
                        'chip' => match ($item['verdict'] ?? null) {
                            'pass', 'na' => 'PASSED',
                            'fail' => 'FAILED',
                            default => 'WAITING',
                        },
                    ];
                }
            }
        }

        $chip = match (true) {
            $total < 1 => 'WAITING',
            $capacityFail => 'FAILED',
            $failed > 0 => 'REFER',
            $decided >= $total => 'PASSED',
            default => 'WAITING',
        };

        $remainingChecks = collect($checks)
            ->filter(fn ($row) => ($row['chip'] ?? '') === 'WAITING' || ($row['chip'] ?? '') === 'FAILED')
            ->values()
            ->all();

        return [
            'chip' => $chip,
            'label' => 'Gate 2 '.$chip,
            'done' => $decided,
            'total' => $total,
            'failed' => $failed,
            'checks' => array_values($checks),
            'remaining_checks' => $remainingChecks,
            'remaining_count' => $openOnDesk,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @return array<string, mixed>
     */
    private function gate2Summary(
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
        array $subjects,
    ): array {
        $rows = [];
        $gateRemaining = 0;
        foreach ($subjects as $subject) {
            $status = $this->personGate2Status($application, $actor, $review, $groupReview, $subject);
            $name = filled($subject['sublabel'] ?? null)
                ? (string) $subject['sublabel']
                : (string) ($subject['label'] ?? 'Participant');
            $gateRemaining += (int) ($status['remaining_count'] ?? 0);
            $rows[] = [
                'name' => $name,
                'role' => $subject['label'] ?? null,
                'chip' => $status['chip'],
                'checks' => $status['checks'] ?? [],
                'remaining_count' => $status['remaining_count'] ?? 0,
                'href' => route('admin.loan-applications.guided-screening', array_filter([
                    'loan_application' => $application,
                    'focus_person' => $subject['person'] ?? null,
                    'focus_m' => $subject['m'] ?? null,
                    'focus_g' => $subject['g'] ?? null,
                ])),
            ];
        }

        $chips = collect($rows)->pluck('chip');
        $overall = match (true) {
            $chips->contains('FAILED') => 'FAILED',
            $chips->contains('REFER') => 'REFER',
            $chips->contains('WAITING') || $rows === [] => 'WAITING',
            default => 'PASSED',
        };

        return [
            'rows' => $rows,
            'overall' => $overall,
            'gate_remaining' => $gateRemaining,
            'continue' => match ($overall) {
                'PASSED' => 'Continue to Gate 3',
                'FAILED' => 'Follow the existing Screening rejection path for this affordability failure.',
                'REFER' => 'Resolve the referred Gate 2 concerns before continuing.',
                default => $gateRemaining === 1
                    ? '1 check remains in Gate 2. Continue Reviewing opens the next one.'
                    : $gateRemaining.' checks remain in Gate 2. Continue Reviewing opens the next one.',
            },
            'can_continue' => $overall === 'PASSED',
        ];
    }

    /**
     * @param  array<string, mixed>  $subject
     * @return array{chip: string, label: string, remaining_count: int, panel?: array<string, mixed>}
     */
    private function personGate3Status(
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
        array $subject,
    ): array {
        $customer = $this->subjectCustomer($application, $review, $subject);
        $panel = app(Gate3CrbPanelService::class)->panel(
            $application,
            $customer,
            [
                'person' => $subject['person'] ?? 'borrower',
                'm' => $subject['m'] ?? null,
                'g' => $subject['g'] ?? null,
            ],
            $actor,
        );

        $person = (string) ($subject['person'] ?? 'borrower');
        $m = isset($subject['m']) ? (int) $subject['m'] : null;
        $g = isset($subject['g']) ? (int) $subject['g'] : null;
        $desk = $this->checklist->deskViewModel($application, $review, $groupReview, $actor, $person, $g, $m);
        $gates = $this->gates->regroup($desk['groups'] ?? [], $application);
        $crbGate = $gates['crb'] ?? null;
        $openOnDesk = 0;
        $failed = (int) ($crbGate['failed'] ?? 0);
        $total = (int) ($crbGate['total'] ?? 0);
        $decided = (int) ($crbGate['decided'] ?? 0);
        foreach ($crbGate['groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if ($this->itemNeedsAction($item)) {
                    $openOnDesk++;
                }
            }
        }

        $chip = (string) ($panel['chip'] ?? 'WAITING');
        if ($chip === 'PASSED' && $openOnDesk > 0) {
            $chip = 'WAITING';
        }
        if ($failed > 0 && $chip === 'PASSED') {
            $chip = 'FAILED';
        }

        return [
            'chip' => $chip,
            'label' => 'Gate 3 '.$chip,
            'done' => $decided,
            'total' => $total,
            'failed' => $failed,
            'remaining_count' => $openOnDesk,
            'panel' => $panel,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @return array<string, mixed>
     */
    private function gate3Summary(
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
        array $subjects,
    ): array {
        $rows = [];
        $gateRemaining = 0;
        foreach ($subjects as $subject) {
            $status = $this->personGate3Status($application, $actor, $review, $groupReview, $subject);
            $name = filled($subject['sublabel'] ?? null)
                ? (string) $subject['sublabel']
                : (string) ($subject['label'] ?? 'Participant');
            $gateRemaining += (int) ($status['remaining_count'] ?? 0);
            $rows[] = [
                'name' => $name,
                'role' => $subject['label'] ?? null,
                'chip' => $status['chip'],
                'remaining_count' => $status['remaining_count'] ?? 0,
                'freshness' => $status['panel']['freshness']['status'] ?? null,
                'subject_match' => ! empty($status['panel']['subject']['matches']),
                'href' => route('admin.loan-applications.guided-screening', array_filter([
                    'loan_application' => $application,
                    'focus_person' => $subject['person'] ?? null,
                    'focus_m' => $subject['m'] ?? null,
                    'focus_g' => $subject['g'] ?? null,
                ])),
            ];
        }

        $chips = collect($rows)->pluck('chip');
        $overall = match (true) {
            $chips->contains('FAILED') => 'FAILED',
            $chips->contains('REFER') => 'REFER',
            $chips->contains('WAITING') || $rows === [] => 'WAITING',
            default => 'PASSED',
        };

        return [
            'rows' => $rows,
            'overall' => $overall,
            'gate_remaining' => $gateRemaining,
            'continue' => match ($overall) {
                'PASSED' => 'Continue to Gate 4 when ready. Gate 3 stays complete for this file.',
                'FAILED' => 'Follow the existing Screening rejection / replacement path for this CRB failure.',
                'REFER' => 'Resolve reviewable CRB findings before Gate 3 can pass.',
                default => $gateRemaining === 1
                    ? '1 check remains in Gate 3. Continue Reviewing opens the next one.'
                    : $gateRemaining.' checks remain in Gate 3. Continue Reviewing opens the next one.',
            },
            'can_continue' => $overall === 'PASSED',
        ];
    }

    public function markStarted(LoanApplication $application): void
    {
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        if (empty($guided['started_at'])) {
            $guided['started_at'] = now()->toIso8601String();
        }
        $guided['last_activity_at'] = now()->toIso8601String();
        $payload['guided'] = $guided;
        $application->update(['screening_payload' => $payload]);
    }

    public function markActivity(LoanApplication $application, array $resume = []): void
    {
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        $guided['last_activity_at'] = now()->toIso8601String();
        if ($resume !== []) {
            $guided['resume'] = $resume;
        }
        $payload['guided'] = $guided;
        $application->update(['screening_payload' => $payload]);
    }

    /**
     * @param  iterable<LoanApplication>  $applications
     * @return array{do_now: list<array<string, mixed>>, waiting: list<array<string, mixed>>, completed: list<array<string, mixed>>}
     */
    public function queue(iterable $applications, ?User $actor = null): array
    {
        $out = [
            self::BUCKET_DO_NOW => [],
            self::BUCKET_WAITING => [],
            self::BUCKET_COMPLETED => [],
        ];
        foreach ($applications as $application) {
            $next = $this->forApplication($application, $actor);
            $out[$next['bucket']][] = [
                'application' => $application,
                'next' => $next,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function firstActionableStep(
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
        array $subjects,
        array $snapshot,
        array $cursor = [],
    ): array {
        $pinItem = (string) ($cursor['at_item'] ?? '');
        $pinPerson = (string) ($cursor['at_person'] ?? 'borrower');
        $pinM = isset($cursor['at_m']) ? (int) $cursor['at_m'] : null;
        $pinG = isset($cursor['at_g']) ? (int) $cursor['at_g'] : null;

        if (($snapshot['pending_rejection'] ?? false) || $this->autoReject->isPending($application)) {
            $waiting = $this->waitingState($application, $snapshot, $groupReview)
                ?? [
                    'kind' => 'policy',
                    'label' => 'Pending automatic rejection',
                    'detail' => 'Screening is paused while this application awaits automatic affordability re-evaluation. No analyst action is required right now.',
                    'gate' => 'declared',
                    'gate_index' => 1,
                    'since' => data_get($application->screening_payload, 'capacity_auto_reject.parked_at'),
                ];

            return $this->waitingStep($waiting, $snapshot);
        }

        if ($pinItem === '' && ! ($snapshot['declared']['pass'] ?? false)) {
            return $this->gateOneStep($application, $snapshot, $groupReview);
        }

        $gate1Seen = (bool) data_get($application->screening_payload, 'guided.seen_gates.declared');
        if ($pinItem === '' && ! $gate1Seen && ! $this->hasLaterHumanWork($application)) {
            return $this->gateOneStep($application, $snapshot, $groupReview);
        }

        if ($pinItem === '' && $this->needsCollateralSecureRequest($application, $snapshot)) {
            return $this->collateralSecureStep($application, $snapshot);
        }

        $gate2Seen = (bool) data_get($application->screening_payload, 'guided.seen_gates.income');
        $gate3Seen = (bool) data_get($application->screening_payload, 'guided.seen_gates.crb');
        $skipItemEarly = (string) ($cursor['after_item'] ?? '');
        if ($pinItem === ''
            && $skipItemEarly === ''
            && ! $gate2Seen
            && $this->incomeGateFullyResolved($subjects, $application, $actor, $review, $groupReview)) {
            return $this->gateTwoPassedStep($subjects, $application, $actor, $review, $groupReview);
        }
        if ($pinItem === ''
            && $skipItemEarly === ''
            && $gate2Seen
            && ! $gate3Seen
            && $this->crbGateFullyResolved($subjects, $application, $actor, $review, $groupReview)) {
            return $this->gateThreePassedStep($subjects, $application, $actor, $review, $groupReview);
        }

        $skipItem = $skipItemEarly;
        $skipPerson = (string) ($cursor['after_person'] ?? '');
        $skipM = isset($cursor['after_m']) ? (int) $cursor['after_m'] : null;
        $skipG = isset($cursor['after_g']) ? (int) $cursor['after_g'] : null;
        $passedCursor = $skipItem === '';

        $subjectTotal = max(1, count($subjects));
        // Gate-first so Borrower ↔ Guarantor can finish the same gate together.
        foreach (['income', 'crb', 'collateral', 'identity', 'final'] as $gateKey) {
            foreach ($this->orderedSubjects($subjects) as $subject) {
                $subjectIndex = $this->subjectIndex($subjects, $subject);
                $person = (string) ($subject['person'] ?? 'borrower');
                $m = isset($subject['m']) ? (int) $subject['m'] : null;
                $g = isset($subject['g']) ? (int) $subject['g'] : null;
                $desk = $this->checklist->deskViewModel($application, $review, $groupReview, $actor, $person, $g, $m);
                $gates = $this->gates->regroup($desk['groups'] ?? [], $application);
                $customer = $this->subjectCustomer($application, $review, $subject);
                $card = $this->checklist->identityPeopleCard($desk, $customer);
                $gate = $gates[$gateKey] ?? null;
                if (! is_array($gate) || ($pinItem === '' && ! empty($gate['locked']))) {
                    continue;
                }
                foreach ($this->orderedGateGroups($gateKey, $gate['groups'] ?? []) as $group) {
                    foreach ($group['items'] ?? [] as $item) {
                        $itemKey = (string) ($item['key'] ?? '');
                        $samePerson = $person === $pinPerson
                            && (int) ($m ?? 0) === (int) ($pinM ?? 0)
                            && (int) ($g ?? 0) === (int) ($pinG ?? 0);
                        if ($pinItem !== '' && $samePerson && $itemKey === $pinItem) {
                            $step = $this->itemStep(
                                $application,
                                $item,
                                $gateKey,
                                $subject,
                                $subjectIndex,
                                $subjectTotal,
                                $card,
                                $customer,
                                $snapshot,
                            );
                            $step['revisiting'] = ($item['verdict'] ?? null) !== null || ! $this->itemNeedsAction($item);

                            return $step;
                        }

                        // Advance the after-cursor even when the marker item is already complete.
                        // Otherwise Save→Continue past a finished statement check skips the whole file
                        // and falls through to a misleading Gate 6 "N requirements remain" screen.
                        if (! $passedCursor) {
                            $sameSkip = $person === $skipPerson
                                && (int) ($m ?? 0) === (int) ($skipM ?? 0)
                                && (int) ($g ?? 0) === (int) ($skipG ?? 0);
                            // Tolerate missing after_g/after_m when only one subject of that person exists.
                            if ($person === $skipPerson && $itemKey === $skipItem && (
                                $sameSkip
                                || ($skipG === null && $skipM === null)
                            )) {
                                $passedCursor = true;
                            }

                            continue;
                        }

                        if (! $this->itemNeedsAction($item) || $pinItem !== '') {
                            continue;
                        }

                        if ($gateKey !== 'income'
                            && $pinItem === ''
                            && ! $gate2Seen
                            && $this->incomeGateFullyResolved($subjects, $application, $actor, $review, $groupReview)) {
                            return $this->gateTwoPassedStep($subjects, $application, $actor, $review, $groupReview);
                        }

                        if ($gateKey !== 'income'
                            && $gateKey !== 'crb'
                            && $pinItem === ''
                            && $gate2Seen
                            && ! $gate3Seen
                            && $this->crbGateFullyResolved($subjects, $application, $actor, $review, $groupReview)) {
                            return $this->gateThreePassedStep($subjects, $application, $actor, $review, $groupReview);
                        }

                        return $this->itemStep(
                            $application,
                            $item,
                            $gateKey,
                            $subject,
                            $subjectIndex,
                            $subjectTotal,
                            $card,
                            $customer,
                            $snapshot,
                        );
                    }
                }
            }
        }

        if ($pinItem !== '') {
            return $this->firstActionableStep($application, $actor, $review, $groupReview, $subjects, $snapshot, array_diff_key($cursor, array_flip(['at_item', 'at_person', 'at_m', 'at_g'])));
        }

        // after_item walk found nothing — retry from the earliest unresolved gate without the cursor.
        if ($skipItem !== '') {
            return $this->firstActionableStep(
                $application,
                $actor,
                $review,
                $groupReview,
                $subjects,
                $snapshot,
                array_diff_key($cursor, array_flip(['after_item', 'after_person', 'after_m', 'after_g'])),
            );
        }

        $remainingBreakdown = $this->unresolvedBreakdown($subjects, $application, $actor, $review, $groupReview);
        $remaining = (int) ($remainingBreakdown['total'] ?? 0);
        $earliest = $remainingBreakdown['earliest_gate'] ?? null;
        if ($remaining > 0 && is_string($earliest) && $earliest !== '') {
            return $this->gateRemainingStep($earliest, $remainingBreakdown, $snapshot);
        }

        return [
            'type' => 'decision',
            'gate' => 'final',
            'gate_index' => 6,
            'gate_label' => 'Final review',
            'title' => 'Screening complete',
            'prompt' => 'All required Screening checks are complete.',
            'primary' => 'Continue to Decision',
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function gateOneStep(LoanApplication $application, array $snapshot, array $groupReview): array
    {
        $policyPeople = collect($snapshot['policy']['participants'] ?? []);
        $members = collect($groupReview['members'] ?? []);
        $rows = $policyPeople->isNotEmpty()
            ? $policyPeople->map(fn ($p) => [
                'name' => $p['participant'] ?? $p['name'] ?? 'Participant',
                'role' => $p['role'] ?? 'participant',
                'pass' => empty($p['hard_fail']) && ($p['gate_1'] ?? 'pass') !== 'fail' && ($p['gate_2'] ?? 'pass') !== 'fail',
            ])->all()
            : ($members->isNotEmpty()
                ? $members->map(fn ($m) => [
                    'name' => $m['name'] ?? 'Member',
                    'role' => $m['role'] ?? 'member',
                    'pass' => ($m['eligible'] ?? false) || ($m['underwriting_status'] ?? '') === 'pass',
                ])->all()
                : [[
                    'name' => $application->customer?->full_name ?? 'Borrower',
                    'role' => 'borrower',
                    'pass' => (bool) ($snapshot['declared']['pass'] ?? false),
                ]]);

        $park = (bool) ($snapshot['pending_rejection'] ?? false);
        $allPass = collect($rows)->every(fn ($r) => ! empty($r['pass'])) && ($snapshot['declared']['pass'] ?? false);

        return [
            'type' => 'gate_1',
            'gate' => 'declared',
            'gate_index' => 1,
            'gate_label' => 'Initial affordability',
            'title' => $park ? 'Initial affordability not met' : 'Gate 1 · Initial affordability',
            'prompt' => $park
                ? (string) ($snapshot['declared']['detail'] ?? 'This file does not meet the configured affordability policy.')
                : 'The system evaluates everyone who must pass this gate. Continue when required participants pass.',
            'participants' => $rows,
            'all_pass' => $allPass,
            'parked' => $park,
            'primary' => $park ? null : 'Continue to Verified Income',
        ];
    }

    /**
     * Gate 2 fully decided for every desk subject — pause before Gate 3 until owner continues.
     *
     * @param  list<array<string, mixed>>  $subjects
     */
    private function incomeGateFullyResolved(
        array $subjects,
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
    ): bool {
        $sawIncome = false;
        foreach ($subjects as $subject) {
            $person = (string) ($subject['person'] ?? 'borrower');
            $m = isset($subject['m']) ? (int) $subject['m'] : null;
            $g = isset($subject['g']) ? (int) $subject['g'] : null;
            $desk = $this->checklist->deskViewModel($application, $review, $groupReview, $actor, $person, $g, $m);
            $gates = $this->gates->regroup($desk['groups'] ?? [], $application);
            $income = $gates['income'] ?? null;
            if (! is_array($income) || (int) ($income['total'] ?? 0) < 1) {
                continue;
            }
            $sawIncome = true;
            foreach ($income['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    if ($this->itemNeedsAction($item)) {
                        return false;
                    }
                }
            }
            if ((int) ($income['failed'] ?? 0) > 0) {
                return false;
            }
        }

        return $sawIncome;
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @return array<string, mixed>
     */
    private function gateTwoPassedStep(
        array $subjects,
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
    ): array {
        $summary = $this->gate2Summary($application, $actor, $review, $groupReview, $subjects);
        $rows = collect($summary['rows'] ?? [])->map(fn ($row) => [
            'name' => $row['name'] ?? 'Participant',
            'role' => $row['role'] ?? 'participant',
            'pass' => ($row['chip'] ?? '') === 'PASSED',
        ])->all();

        return [
            'type' => 'gate_2_passed',
            'gate' => 'income',
            'gate_index' => 2,
            'gate_label' => 'Verified affordability',
            'title' => 'Gate 2 · Verified affordability',
            'prompt' => 'All required participants passed Gate 2.',
            'participants' => $rows,
            'all_pass' => ($summary['overall'] ?? '') === 'PASSED',
            'primary' => 'Continue to Gate 3',
        ];
    }

    /**
     * Gate 3 fully decided for every desk subject — pause before Gate 4 until owner continues.
     *
     * @param  list<array<string, mixed>>  $subjects
     */
    private function crbGateFullyResolved(
        array $subjects,
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
    ): bool {
        $summary = $this->gate3Summary($application, $actor, $review, $groupReview, $subjects);
        if (($summary['overall'] ?? '') !== 'PASSED') {
            return false;
        }
        $sawCrb = false;
        foreach ($subjects as $subject) {
            $person = (string) ($subject['person'] ?? 'borrower');
            $m = isset($subject['m']) ? (int) $subject['m'] : null;
            $g = isset($subject['g']) ? (int) $subject['g'] : null;
            $desk = $this->checklist->deskViewModel($application, $review, $groupReview, $actor, $person, $g, $m);
            $gates = $this->gates->regroup($desk['groups'] ?? [], $application);
            $crb = $gates['crb'] ?? null;
            if (! is_array($crb) || (int) ($crb['total'] ?? 0) < 1) {
                continue;
            }
            $sawCrb = true;
            foreach ($crb['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    if ($this->itemNeedsAction($item)) {
                        return false;
                    }
                }
            }
            if ((int) ($crb['failed'] ?? 0) > 0) {
                return false;
            }
        }

        return $sawCrb;
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @return array<string, mixed>
     */
    private function gateThreePassedStep(
        array $subjects,
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
    ): array {
        $summary = $this->gate3Summary($application, $actor, $review, $groupReview, $subjects);
        $rows = collect($summary['rows'] ?? [])->map(fn ($row) => [
            'name' => $row['name'] ?? 'Participant',
            'role' => $row['role'] ?? 'participant',
            'pass' => ($row['chip'] ?? '') === 'PASSED',
            'chip' => $row['chip'] ?? 'WAITING',
        ])->all();

        return [
            'type' => 'gate_3_passed',
            'gate' => 'crb',
            'gate_index' => 3,
            'gate_label' => 'CRB / Credit history',
            'title' => 'Gate 3 · CRB & credit history',
            'prompt' => 'All required participants cleared Gate 3.',
            'participants' => $rows,
            'all_pass' => ($summary['overall'] ?? '') === 'PASSED',
            'gate3_summary' => $summary,
            'primary' => 'Continue to Gate 4',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $card
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function itemStep(
        LoanApplication $application,
        array $item,
        string $gateKey,
        array $subject,
        int $subjectIndex,
        int $subjectTotal,
        array $card,
        ?Customer $customer,
        array $snapshot,
    ): array {
        $key = (string) ($item['key'] ?? '');
        [$groupKey, $itemKey] = array_pad(explode('.', $key, 2), 2, '');
        $index = ScreeningSequenceService::gateIndex($gateKey);
        $requestable = $this->requestablePreset($key, $item);
        $contact = $this->contactContext($key, $card, $customer, $item['destination'] ?? null);
        $isSystemItem = ! empty($item['system_determined'])
            || ! empty($item['catalog_system'])
            || ! empty($item['system_checked'])
            || ! empty($item['documents_checked'])
            || $this->gates->isSystemFail($item);
        $isRequest = $requestable && (
            ! empty($item['awaiting_data'])
            || (($item['verdict'] ?? null) === 'fail' && $this->isMissingEvidenceFail($item))
        );
        $isAttention = $isSystemItem && ! $isRequest && empty($item['captures_statement']);
        $factCapture = ! empty($item['captures_statement'])
            && ! in_array($item['verdict'] ?? null, ['pass', 'fail', 'na'], true);
        $outcomes = $factCapture || (! empty($item['captures_statement']) && ! empty($item['system_determined']))
            ? []
            : $this->outcomesFor($key);

        $gate3Panel = null;
        if ($gateKey === 'crb') {
            $gate3Panel = app(Gate3CrbPanelService::class)->panel(
                $application,
                $customer,
                [
                    'person' => $subject['person'] ?? 'borrower',
                    'm' => $subject['m'] ?? null,
                    'g' => $subject['g'] ?? null,
                ],
                auth()->user() instanceof User ? auth()->user() : null,
            );
            // Do not ask Pass/Concern for system identity findings or secondary review while blocked.
            if (! empty($gate3Panel['block_secondary'])
                || in_array($key, ['identity.name_vs_crb', 'identity.marital_vs_crb'], true)
                || str_contains($key, 'crb_reviewed')) {
                if (! empty($gate3Panel['block_secondary']) || $isSystemItem) {
                    $isAttention = true;
                    $outcomes = [];
                }
                // Reviewable marital/refer: panel carries accept forms — not Pass/Concern radios.
                if (in_array($key, ['identity.marital_vs_crb'], true)
                    && empty($gate3Panel['block_secondary'])
                    && ($item['verdict'] ?? null) === null) {
                    $isAttention = true;
                    $outcomes = [];
                }
                if (str_contains($key, 'crb_reviewed')
                    && empty($gate3Panel['block_secondary'])
                    && in_array(($gate3Panel['chip'] ?? ''), ['REFER', 'FAILED', 'WAITING'], true)
                    && ($item['verdict'] ?? null) === null) {
                    // Keep human Pass only when chip is clear enough; REFER uses accept on findings.
                    if (($gate3Panel['chip'] ?? '') === 'REFER') {
                        $isAttention = true;
                        $outcomes = [];
                    }
                }
            }
        }

        $base = [
            'gate' => $gateKey,
            'gate_index' => $index,
            'gate_label' => ScreeningSequenceService::SEQUENCE[$gateKey === 'income' ? 'income' : $gateKey]
                ?? ($this->gates::GATES[$gateKey] ?? $gateKey),
            'title' => (string) ($item['label'] ?? 'Review'),
            'prompt' => $item['awaiting_message'] ?? $item['fail_reason_label'] ?? $this->defaultPrompt($key),
            'why' => $this->why($key),
            'item_key' => $key,
            'group_key' => $groupKey,
            'item_short' => $itemKey,
            'fail_reasons' => $item['fail_reasons'] ?? [],
            'verdict' => $item['verdict'] ?? null,
            'fail_reason_code' => $item['fail_reason_code'] ?? null,
            'awaiting_data' => ! empty($item['awaiting_data']),
            'requestable' => $requestable,
            'destination' => $item['destination'] ?? null,
            'contact' => $contact,
            'participant' => [
                'label' => $subject['label'] ?? 'Participant',
                'person' => $subject['person'] ?? 'borrower',
                'm' => $subject['m'] ?? null,
                'g' => $subject['g'] ?? null,
                'index' => $subjectIndex,
                'total' => $subjectTotal,
                'name' => $customer?->full_name ?? ($subject['sublabel'] ?? null),
            ],
            'evidence' => $item['evidence'] ?? [],
            'captures_statement' => ! empty($item['captures_statement']),
            'statement_deposits_total' => $item['statement_deposits_total'] ?? null,
            'statement_monthly' => $item['statement_monthly'] ?? null,
            'declared_monthly_income' => $item['declared_monthly_income'] ?? null,
            'income_basis' => $item['income_basis'] ?? null,
            'affordability_income' => $item['affordability_income'] ?? null,
            'notes' => $item['notes'] ?? '',
            'statement_comparison' => $item['statement_comparison'] ?? null,
            'capacity' => $item['capacity'] ?? null,
            'declared_income_label' => $item['declared_income_label'] ?? null,
            'gate2_panel' => $item['gate2_panel'] ?? null,
            'gate2_checks' => $this->gate2CheckStatuses(
                $application,
                auth()->user() instanceof User ? auth()->user() : null,
                [
                    'person' => $subject['person'] ?? 'borrower',
                    'm' => $subject['m'] ?? null,
                    'g' => $subject['g'] ?? null,
                ],
                $gateKey,
            ),
            'gate3_panel' => $gate3Panel,
            'system_determined' => ! empty($item['system_determined']) || ($isSystemItem && ! $factCapture),
            'fact_capture' => $factCapture,
            'note_label' => $this->noteLabel($key),
        ];

        if ($isAttention) {
            return array_merge($base, [
                'type' => 'attention',
                'recommended' => [
                    'label' => 'Needs attention',
                    'detail' => $item['fail_reason_label'] ?? 'The system recorded a finding that still needs to be resolved.',
                ],
                'primary' => 'Continue reviewing',
                'outcomes' => [],
            ]);
        }

        return array_merge($base, [
            'type' => $isRequest ? 'request' : 'human',
            'recommended' => $isRequest ? [
                'label' => 'Required next step',
                'detail' => $requestable['label'] ?? 'Request the missing evidence',
            ] : null,
            'primary' => $requestable && $isRequest
                ? 'Request & pause'
                : ($factCapture ? 'Save & Next' : 'Save & Next'),
            'outcomes' => $outcomes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $waiting
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function waitingStep(array $waiting, array $snapshot): array
    {
        return [
            'type' => 'waiting',
            'gate' => $waiting['gate'] ?? 'identity',
            'gate_index' => ScreeningSequenceService::gateIndex((string) ($waiting['gate'] ?? 'identity')),
            'gate_label' => $waiting['label'] ?? 'Waiting',
            'title' => $waiting['label'] ?? 'Screening paused',
            'prompt' => $waiting['detail'] ?? 'We will continue from this step when the required information is received.',
            'waiting' => $waiting,
            'primary' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function needsCollateralSecureRequest(LoanApplication $application, array $snapshot): bool
    {
        if (! ($snapshot['unlocked']['collateral'] ?? false)) {
            return false;
        }
        if ($this->collateralSecure->isAwaitingCustomerCollateral($application)
            || $this->collateralSecure->isOpen($application)) {
            return false;
        }
        if (is_asset_backed_loan_product($application->product?->code)
            || is_marketplace_loan_product($application->product?->code)) {
            return false;
        }
        $application->loadMissing('collateralAssets');
        if ($application->collateralAssets->isNotEmpty()) {
            return false;
        }

        return app(LoanPolicyService::class)->applicationRequiresCollateral($application);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function collateralSecureStep(LoanApplication $application, array $snapshot): array
    {
        $who = filled($application->loan_group_id) ? 'group leader' : 'borrower';

        return [
            'type' => 'collateral_secure',
            'gate' => 'collateral',
            'gate_index' => ScreeningSequenceService::gateIndex('collateral'),
            'gate_label' => 'Collateral & security',
            'title' => 'Collateral is required',
            'prompt' => 'This file needs pledged security. Ask the '.$who.' to add it on their loan profile. Do not assign a valuer to pass this gate.',
            'primary' => 'Review & request collateral',
            'recommended' => [
                'label' => 'Required next step',
                'detail' => 'Request collateral via the existing loan-profile journey.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function gateCompleteStep(array $snapshot): array
    {
        $next = $snapshot['next_action'] ?? [];

        return [
            'type' => 'gate_complete',
            'gate' => 'final',
            'gate_index' => 6,
            'gate_label' => 'Final review',
            'title' => $next['label'] ?? 'Continue screening',
            'prompt' => $next['detail'] ?? '',
            'primary' => $next['cta'] ?? 'Continue',
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $groupReview
     * @return array<string, mixed>|null
     */
    private function waitingState(LoanApplication $application, array $snapshot, array $groupReview): ?array
    {
        // Capacity park dominates ordinary waiting (guarantor / docs / collateral).
        if (($snapshot['pending_rejection'] ?? false) || $this->autoReject->isPending($application)) {
            $park = is_array($snapshot['park'] ?? null) ? $snapshot['park'] : ($this->autoReject->state($application) ?? []);
            $detailParts = [
                'Screening is paused while this application awaits automatic affordability re-evaluation. No analyst action is required right now.',
                (($snapshot['park_gate'] ?? ($park['gate'] ?? '')) === 'verified')
                    ? 'Verified affordability failed.'
                    : 'Declared affordability failed.',
            ];
            if (! empty($snapshot['remaining_label'])) {
                $detailParts[] = $snapshot['remaining_label'].' remaining.';
            } else {
                $remaining = $this->autoReject->remainingLabel($application);
                if (filled($remaining)) {
                    $detailParts[] = $remaining.' remaining.';
                }
            }
            if (! empty($park['parked_at'])) {
                try {
                    $detailParts[] = 'Parked '. \Illuminate\Support\Carbon::parse($park['parked_at'])->timezone(config('app.timezone'))->format('d M Y H:i').'.';
                } catch (\Throwable) {
                }
            }
            if (! empty($park['auto_reject_at'])) {
                try {
                    $detailParts[] = 'Scheduled re-evaluation '. \Illuminate\Support\Carbon::parse($park['auto_reject_at'])->timezone(config('app.timezone'))->format('d M Y H:i').'.';
                } catch (\Throwable) {
                }
            }

            return [
                'kind' => 'policy',
                'label' => 'Pending automatic rejection',
                'detail' => implode(' ', $detailParts),
                'gate' => 'declared',
                'gate_index' => 1,
                'since' => $park['parked_at'] ?? null,
            ];
        }

        $supplement = data_get($application->screening_payload, 'guarantor_supplement');
        if (is_array($supplement)
            && ($supplement['kind'] ?? '') === 'change'
            && empty($supplement['satisfied_at'])) {
            return [
                'kind' => 'guarantor',
                'label' => 'Waiting for guarantor',
                'detail' => 'The borrower has been asked to provide a replacement guarantor.',
                'gate' => 'declared',
                'gate_index' => 1,
                'since' => $supplement['requested_at'] ?? data_get($application->screening_payload, 'guided.waiting_since'),
            ];
        }

        if ($this->collateralSecure->isAwaitingCustomerCollateral($application)) {
            $state = $this->collateralSecure->state($application) ?? [];

            return [
                'kind' => 'collateral',
                'label' => 'Waiting for collateral',
                'detail' => 'Collateral is required and has not been pledged yet.',
                'gate' => 'collateral',
                'gate_index' => ScreeningSequenceService::gateIndex('collateral'),
                'since' => $state['requested_at'] ?? data_get($application->screening_payload, 'guided.waiting_since'),
            ];
        }

        $open = $application->documentRequests
            ->filter(fn ($req) => $this->documents->isWaitingOnBorrower($req))
            ->first();
        if ($open instanceof LoanApplicationDocumentRequest) {
            $kind = $this->documents->borrowerActionKind($open);
            $isCollateral = $kind === 'collateral';
            [$waitKind, $waitLabel] = $this->documentWaitingBucket($open, $groupReview, $isCollateral);

            return [
                'kind' => $waitKind,
                'label' => $waitLabel,
                'detail' => $open->label.' is outstanding. Due '.optional($open->due_at)->timezone(config('app.timezone'))->format('d M Y').'.',
                'gate' => $isCollateral ? 'collateral' : 'identity',
                'gate_index' => ScreeningSequenceService::gateIndex($isCollateral ? 'collateral' : 'identity'),
                'request_id' => $open->id,
                'since' => optional($open->created_at)->toIso8601String(),
            ];
        }

        $replacing = collect($groupReview['members'] ?? [])
            ->first(fn ($m) => ($m['underwriting_status'] ?? '') === 'replacement_requested');
        if (is_array($replacing)) {
            return [
                'kind' => 'group_leader',
                'label' => 'Waiting for group leader',
                'detail' => 'Replace '.($replacing['name'] ?? 'member'),
                'gate' => 'declared',
                'gate_index' => 1,
                'since' => null,
            ];
        }

        return null;
    }

    /**
     * Screening Home waiting labels come from who must act, not translated copy.
     *
     * @param  array<string, mixed>  $groupReview
     * @return array{0: string, 1: string}
     */
    private function documentWaitingBucket(
        LoanApplicationDocumentRequest $request,
        array $groupReview,
        bool $isCollateral,
    ): array {
        if ($isCollateral) {
            return ['collateral', 'Waiting for collateral'];
        }

        $role = $request->subjectRoleLabel($groupReview);

        return match (true) {
            str_contains($role, 'Guarantor') => ['guarantor', 'Waiting for guarantor'],
            str_contains($role, 'Member') => ['member', 'Waiting for member'],
            str_contains($role, 'Leader') => ['group_leader', 'Waiting for group leader'],
            default => ['document', 'Waiting for document'],
        };
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function subjectCustomer(LoanApplication $application, array $review, array $subject): ?Customer
    {
        $id = (int) ($subject['customer_id'] ?? 0);
        if ($id > 0) {
            $found = Customer::query()->find($id);
            if ($found) {
                return $found;
            }
        }
        $fromReview = $review['customer'] ?? $application->customer;

        return $fromReview instanceof Customer ? $fromReview : null;
    }

    /**
     * Compact Gate 2 sub-check status for the capacity card (capacity vs remaining observations).
     *
     * @param  array<string, mixed>  $subject
     * @return list<array{label: string, chip: string}>|null
     */
    private function gate2CheckStatuses(
        LoanApplication $application,
        ?User $actor,
        array $subject,
        string $gateKey,
    ): ?array {
        if ($gateKey !== 'income') {
            return null;
        }

        $person = (string) ($subject['person'] ?? 'borrower');
        $m = isset($subject['m']) ? (int) $subject['m'] : null;
        $g = isset($subject['g']) ? (int) $subject['g'] : null;
        $desk = $this->checklist->deskViewModel($application, [], [], $actor, $person, $g, $m);
        $gates = $this->gates->regroup($desk['groups'] ?? [], $application);
        $income = $gates['income'] ?? null;
        if (! is_array($income)) {
            return null;
        }

        $byKey = [];
        foreach ($income['groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $byKey[(string) ($item['key'] ?? '')] = $item;
            }
        }

        $chipFor = function (?array $item, bool $capacity = false) {
            if (! is_array($item)) {
                return 'WAITING';
            }
            if ($capacity && is_array($item['capacity'] ?? null)) {
                $cap = $item['capacity'];
                if (($cap['income_basis'] ?? '') === 'statement') {
                    return ! empty($cap['capacity_pass']) ? 'PASSED' : 'FAILED';
                }

                return 'WAITING';
            }
            $verdict = $item['verdict'] ?? null;
            if ($verdict === 'pass' || $verdict === 'na') {
                return 'PASSED';
            }
            if ($verdict === 'fail') {
                return 'FAILED';
            }

            return 'WAITING';
        };

        $evidence = $byKey[StatementCapacityService::CHECKLIST_KEY] ?? null;
        $activity = $byKey['activity_income.activity_plausible'] ?? null;
        $pattern = $byKey['activity_income.bank_or_mobile_money'] ?? null;

        $rows = [
            ['label' => 'Capacity check', 'chip' => $chipFor($evidence, true)],
            ['label' => 'Activity check', 'chip' => $chipFor($activity)],
            ['label' => 'Pattern check', 'chip' => $chipFor($pattern)],
        ];

        $personStatus = $this->personGate2Status($application, $actor, [], [], $subject);
        $rows[] = ['label' => 'GATE 2', 'chip' => $personStatus['chip'] ?? 'WAITING'];

        return $rows;
    }

    /**
     * Gate 2 must present financial evidence before contact/capacity wrap items.
     *
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function orderedGateGroups(string $gateKey, array $groups): array
    {
        if ($gateKey !== 'income') {
            return $groups;
        }

        return collect($groups)
            ->sortBy(function (array $group) {
                $key = (string) ($group['key'] ?? '');

                return match ($key) {
                    'activity_income' => 0,
                    'contacts' => 1,
                    'guarantor_wrap', 'member_wrap' => 2,
                    default => 3,
                };
            })
            ->values()
            ->all();
    }

    /**
     * Document-request composer only for missing evidence — not capacity/discrepancy fails.
     *
     * @param  array<string, mixed>  $item
     */
    private function isMissingEvidenceFail(array $item): bool
    {
        $code = (string) ($item['fail_reason_code'] ?? '');

        return in_array($code, [
            'statements_missing',
            'nida_missing',
            'nida_malformed',
            'nida_incomplete',
            'face_photo_missing',
            'id_photo_missing',
            'photos_missing',
            'proof_missing',
            'document_unclear',
            'poor_quality',
            'wrong_document',
            'proof_invalid',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemNeedsAction(array $item): bool
    {
        if ($this->gates->isQuietAuto($item)) {
            return false;
        }
        if (app(ScreeningExceptionService::class)->isAccepted($item)) {
            return false;
        }

        $systemish = ! empty($item['catalog_system'])
            || ! empty($item['system_checked'])
            || ! empty($item['documents_checked']);
        $systemOpen = $systemish && ! in_array($item['verdict'] ?? null, ['pass', 'na'], true);
        $humanOpen = ($item['verdict'] ?? null) === null && $this->gates->isHumanWork($item);
        $needsResolution = $this->gates->isSystemFail($item) || ! empty($item['awaiting_data']);
        $needsRequest = ($item['verdict'] ?? null) === 'fail'
            && $this->requestablePreset((string) ($item['key'] ?? ''), $item) !== null;

        return $humanOpen || $needsResolution || $needsRequest || $systemOpen;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{label: string, preset: string}|null
     */
    private function requestablePreset(string $key, array $item): ?array
    {
        $code = (string) ($item['fail_reason_code'] ?? '');
        if (in_array($key, ['identity.nida_vs_dob'], true)
            || in_array($code, ['nida_missing', 'nida_malformed', 'nida_incomplete'], true)
            || ($key === 'identity.id_document_quality' && ! empty($item['awaiting_data']))) {
            return [
                'headline' => 'National ID required',
                'label' => 'Request National ID',
                'preset' => 'Updated National ID',
                'reason' => 'National ID is not on this member\'s profile.',
            ];
        }
        if ($key === 'identity.face_vs_nida' || in_array($code, ['face_photo_missing', 'photos_missing'], true)) {
            return ['label' => 'Request face photo', 'preset' => 'Image Not Clear'];
        }
        if ($key === 'identity.id_document_quality' || in_array($code, ['id_photo_missing', 'poor_quality', 'proof_missing', 'document_unclear'], true)) {
            return [
                'headline' => 'National ID required',
                'label' => 'Request National ID',
                'preset' => 'Updated National ID',
                'reason' => 'National ID is not on this member\'s profile.',
                'alternatives' => [
                    ['label' => 'Request ID photo', 'preset' => 'New National ID photo'],
                    ['label' => 'Request face photo', 'preset' => 'Image Not Clear'],
                ],
            ];
        }
        if ($key === 'residence.utility_or_proof' || in_array($code, ['proof_missing', 'proof_invalid', 'document_unclear', 'wrong_document'], true)) {
            return [
                'label' => 'Request residence proof',
                'preset' => 'Updated residence proof',
                'alternatives' => [
                    ['label' => 'LGO / residence letter', 'preset' => 'Guarantor residence letter'],
                ],
            ];
        }
        if (str_starts_with($key, 'activity_income.income_evidence') || $key === 'activity_income.income_evidence') {
            return [
                'headline' => 'Bank statement required',
                'label' => 'Request statement',
                'preset' => 'Updated Bank Statement',
                'reason' => 'Bank / mobile-money statement evidence is required for this Gate 2 check.',
                'alternatives' => [
                    ['label' => 'Mobile money statement', 'preset' => 'Updated Mobile Money Statement'],
                    ['label' => 'Salary slip', 'preset' => 'Latest salary slip'],
                ],
            ];
        }
        if (str_starts_with($key, 'collateral.')) {
            return [
                'label' => 'Request collateral document',
                'preset' => 'Updated collateral ownership document',
                'alternatives' => [
                    ['label' => 'Collateral photo', 'preset' => 'New collateral photo'],
                    ['label' => 'Insurance certificate', 'preset' => 'New Insurance Certificate'],
                ],
            ];
        }
        if (in_array($code, ['id_photo_missing'], true)) {
            return ['label' => 'Request ID photo', 'preset' => 'New National ID photo'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array<string, mixed>|null  $destination
     * @return array<string, mixed>|null
     */
    private function contactContext(string $key, array $card, ?Customer $customer, ?array $destination = null): ?array
    {
        return match ($key) {
            'contacts.call_next_of_kin' => [
                'kind' => 'nok',
                'name' => $card['nok']['name'] ?? null,
                'detail' => $card['nok']['relationship'] ?? null,
                'phone' => $card['nok']['phone'] ?? null,
            ],
            'residence.local_government' => [
                'kind' => 'lgo',
                'name' => $card['lgo']['name'] ?? null,
                'detail' => $card['lgo']['position'] ?? null,
                'phone' => $card['lgo']['phone'] ?? null,
            ],
            'contacts.call_spouse' => [
                'kind' => 'spouse',
                'name' => $card['spouse']['name'] ?? null,
                'detail' => 'Spouse',
                'phone' => $card['spouse']['phone'] ?? null,
            ],
            default => $customer ? [
                'kind' => 'person',
                'name' => $customer->full_name,
                'detail' => filled($customer->national_id) ? (string) $customer->national_id : null,
                'phone' => $customer->phone,
                'national_id' => $customer->national_id,
                'national_id_missing' => ! filled($customer->national_id),
                'profile_href' => $destination['profile_href'] ?? null,
            ] : null,
        };
    }

    /** @return list<array{value: string, label: string, fail_reason_code?: string}> */
    private function outcomesFor(string $key): array
    {
        if ($key === 'activity_income.activity_plausible') {
            return [
                ['value' => 'pass', 'label' => 'Yes — activity supports the stated income'],
                ['value' => 'fail', 'label' => 'No — activity does not support the stated income'],
            ];
        }
        if ($key === 'activity_income.bank_or_mobile_money') {
            return [
                ['value' => 'pass', 'label' => 'No concerning patterns'],
                ['value' => 'fail', 'label' => 'Yes — concerning pattern observed'],
            ];
        }
        if ($key === 'residence.local_government') {
            return [
                ['value' => 'pass', 'label' => 'Confirmed'],
                ['value' => 'fail', 'label' => 'Information differs', 'fail_reason_code' => 'lgo_not_confirmed'],
                ['value' => 'fail', 'label' => 'Could not reach', 'fail_reason_code' => 'lgo_unreachable'],
            ];
        }
        if (str_starts_with($key, 'contacts.call_')) {
            return [
                ['value' => 'pass', 'label' => 'Confirmed'],
                ['value' => 'fail', 'label' => 'Information differs', 'fail_reason_code' => 'information_differs'],
                ['value' => 'fail', 'label' => 'Could not reach', 'fail_reason_code' => 'could_not_reach'],
                ['value' => 'fail', 'label' => 'Invalid contact', 'fail_reason_code' => 'invalid_contact'],
            ];
        }
        if (str_contains($key, 'photo')
            || str_contains($key, 'valuation_or_photos')
            || str_contains($key, 'face_vs')
            || str_contains($key, 'asset_identity')) {
            return [
                ['value' => 'pass', 'label' => 'Matches'],
                ['value' => 'fail', 'label' => 'Concern'],
            ];
        }

        return [
            ['value' => 'pass', 'label' => 'Pass'],
            ['value' => 'fail', 'label' => 'Concern'],
        ];
    }

    private function noteLabel(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'contacts.call_') || $key === 'residence.local_government' => 'Verification note',
            $key === 'activity_income.bank_or_mobile_money',
            $key === 'activity_income.activity_plausible' => 'Explain concern',
            default => 'Note',
        };
    }

    private function defaultPrompt(string $key): string
    {
        return match (true) {
            str_contains($key, 'next_of_kin') => 'Were you able to confirm the next-of-kin information?',
            str_contains($key, 'local_government') => 'Were you able to verify the Local Government Officer?',
            str_contains($key, 'spouse') => 'Were you able to confirm the spouse details?',
            str_contains($key, 'income_evidence') => 'Key the six-month deposit total. Kopafasta compares it with declared income and calculates repayment capacity.',
            str_contains($key, 'activity_plausible') => 'Does the financial activity support the declared income?',
            str_contains($key, 'bank_or_mobile') => 'Did you observe any concerning patterns on the statements?',
            default => 'Record the observation for this check.',
        };
    }

    private function why(string $key): string
    {
        $catalog = config('screening_checklist');
        [$group, $item] = array_pad(explode('.', $key, 2), 2, '');
        $fromConfig = data_get($catalog, $group.'.items.'.$item.'.why');
        if (is_string($fromConfig) && $fromConfig !== '') {
            return $fromConfig;
        }

        return match (true) {
            str_contains($key, 'next_of_kin') => 'This helps confirm that the people around the applicant are real and reachable.',
            str_contains($key, 'local_government') => 'This helps confirm that the applicant\'s residence information is genuine.',
            str_contains($key, 'nida') => 'National ID is the identity the rest of Screening relies on.',
            str_contains($key, 'face') => 'Face matching reduces impersonation risk before credit is granted.',
            str_contains($key, 'activity') => 'Statement activity should be consistent with the income used for affordability.',
            str_contains($key, 'crb') => 'Bureau history shows existing facilities and arrears that affect this decision.',
            str_contains($key, 'collateral') => 'Security must match the pledged asset before the facility can be approved.',
            default => 'This check is required by the current Screening policy for this product.',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @return array{done: int, total: int, percent: int, remaining: int}
     */
    private function checklistProgress(array $subjects): array
    {
        $done = 0;
        $total = 0;
        foreach ($subjects as $subject) {
            $done += (int) ($subject['done'] ?? 0);
            $total += (int) ($subject['total'] ?? 0);
        }

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            'remaining' => max(0, $total - $done),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     */
    private function unresolvedCount(
        array $subjects,
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
    ): int {
        return (int) ($this->unresolvedBreakdown($subjects, $application, $actor, $review, $groupReview)['total'] ?? 0);
    }

    /**
     * Public diagnostic: outstanding Guided Review checklist rows by gate/person.
     *
     * @return array{
     *   total: int,
     *   earliest_gate: ?string,
     *   by_gate: array<string, int>,
     *   by_gate_person: array<string, array<string, int>>,
     *   rows: list<array{key: string, label: string, gate: string, person: string, status: string}>
     * }
     */
    public function outstandingRequirements(LoanApplication $application, ?User $actor = null): array
    {
        $actor = $actor ?? auth()->user();
        $application->loadMissing(['customer', 'product', 'loanGroup.members.customer', 'documentRequests', 'customerGuarantors.invitation']);
        $review = app(LoanApplicationReviewService::class)->dossier($application);
        try {
            $groupReview = app(GroupLoanReviewService::class)->dossier($application) ?? [];
        } catch (\Throwable $e) {
            report($e);
            $groupReview = [];
        }
        if (! is_array($groupReview)) {
            $groupReview = [];
        }
        $subjects = $this->checklist->deskSubjects($application, $review, $groupReview, $actor);

        return $this->unresolvedBreakdown($subjects, $application, $actor, $review, $groupReview);
    }

    /**
     * Full outstanding checklist rows that still need Guided Review action.
     *
     * @param  list<array<string, mixed>>  $subjects
     * @return array{
     *   total: int,
     *   earliest_gate: ?string,
     *   by_gate: array<string, int>,
     *   by_gate_person: array<string, array<string, int>>,
     *   rows: list<array{key: string, label: string, gate: string, person: string, status: string}>
     * }
     */
    private function unresolvedBreakdown(
        array $subjects,
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
    ): array {
        $gateOrder = ['income' => 2, 'crb' => 3, 'collateral' => 4, 'identity' => 5, 'final' => 6];
        $byGate = [];
        $byGatePerson = [];
        $rows = [];
        $earliest = null;
        $earliestRank = 99;

        foreach ($subjects as $subject) {
            $person = (string) ($subject['person'] ?? 'borrower');
            $m = isset($subject['m']) ? (int) $subject['m'] : null;
            $g = isset($subject['g']) ? (int) $subject['g'] : null;
            $name = filled($subject['sublabel'] ?? null)
                ? (string) $subject['sublabel']
                : (string) ($subject['label'] ?? $person);
            $desk = $this->checklist->deskViewModel($application, $review, $groupReview, $actor, $person, $g, $m);
            $gates = $this->gates->regroup($desk['groups'] ?? [], $application);
            foreach ($gates as $gateKey => $gate) {
                foreach ($gate['groups'] ?? [] as $group) {
                    foreach ($group['items'] ?? [] as $item) {
                        if (! $this->itemNeedsAction($item)) {
                            continue;
                        }
                        $byGate[$gateKey] = ($byGate[$gateKey] ?? 0) + 1;
                        $byGatePerson[$gateKey][$name] = ($byGatePerson[$gateKey][$name] ?? 0) + 1;
                        $rows[] = [
                            'key' => (string) ($item['key'] ?? ''),
                            'label' => (string) ($item['label'] ?? $item['key'] ?? 'Check'),
                            'gate' => (string) $gateKey,
                            'person' => $name,
                            'person_kind' => $person,
                            'status' => ($item['verdict'] ?? null) === 'fail' ? 'fail' : 'open',
                            'applicable' => true,
                        ];
                        $rank = $gateOrder[$gateKey] ?? 50;
                        if ($rank < $earliestRank) {
                            $earliestRank = $rank;
                            $earliest = (string) $gateKey;
                        }
                    }
                }
            }
        }

        return [
            'total' => count($rows),
            'earliest_gate' => $earliest,
            'by_gate' => $byGate,
            'by_gate_person' => $byGatePerson,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $breakdown
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function gateRemainingStep(string $gateKey, array $breakdown, array $snapshot): array
    {
        $index = ScreeningSequenceService::gateIndex($gateKey === 'income' ? 'income' : $gateKey);
        $label = ScreeningSequenceService::SEQUENCE[$gateKey]
            ?? ($this->gates::GATES[$gateKey] ?? $gateKey);
        $gateCount = (int) ($breakdown['by_gate'][$gateKey] ?? 0);
        $overall = (int) ($breakdown['total'] ?? 0);
        $people = $breakdown['by_gate_person'][$gateKey] ?? [];

        return [
            'type' => 'gate_remaining',
            'gate' => $gateKey,
            'gate_index' => $index > 0 ? $index : 2,
            'gate_label' => $label,
            'title' => $label,
            'prompt' => $gateCount === 1
                ? '1 check remains in this gate.'
                : $gateCount.' checks remain in this gate.',
            'gate_remaining' => $gateCount,
            'overall_remaining' => $overall,
            'people_remaining' => $people,
            'primary' => 'Continue Reviewing',
            'recommended' => [
                'label' => 'Current gate',
                'detail' => $gateCount.' of this gate remain. '
                    .($overall > $gateCount ? $overall.' checks remain overall across later gates.' : 'Finish this gate before moving on.'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  list<array<string, mixed>>  $subjects
     * @return array{done: int, total: int, label: string}
     */
    private function gateProgressForStep(
        array $step,
        array $subjects,
        LoanApplication $application,
        ?User $actor,
        array $review,
        array $groupReview,
        array $snapshot = [],
    ): array {
        $gateKey = (string) ($step['gate'] ?? '');
        $person = (string) ($step['participant']['person'] ?? 'borrower');
        $m = isset($step['participant']['m']) ? (int) $step['participant']['m'] : null;
        $g = isset($step['participant']['g']) ? (int) $step['participant']['g'] : null;
        $desk = $this->checklist->deskViewModel($application, $review, $groupReview, $actor, $person, $g, $m);
        $gates = $this->gates->regroup($desk['groups'] ?? [], $application);
        $gate = $gates[$gateKey] ?? null;
        $total = (int) ($gate['total'] ?? 0);
        $done = (int) ($gate['decided'] ?? 0);

        return [
            'done' => $done,
            'total' => $total,
            'failed' => (int) ($gate['failed'] ?? 0),
            'label' => $total > 0
                ? 'Gate '.((int) ($step['gate_index'] ?? 0)).' · '.$done.' of '.$total.' applicable checks complete'
                : (string) ($step['gate_label'] ?? ''),
            'result' => $this->gateResultLabel($gateKey, $gate, $snapshot),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $gate
     * @param  array<string, mixed>  $snapshot
     */
    private function gateResultLabel(string $gateKey, ?array $gate, array $snapshot): string
    {
        foreach ((array) ($snapshot['sequence'] ?? $snapshot['rows'] ?? []) as $row) {
            if (($row['key'] ?? '') === $gateKey || ($row['desk_gate'] ?? '') === $gateKey) {
                return match ((string) ($row['status'] ?? '')) {
                    'passed' => 'PASSED',
                    'pending_rejection', 'fail' => 'FAILED',
                    'attention' => 'REFER',
                    'in_progress', 'open' => 'WAITING',
                    default => strtoupper((string) ($row['chip'] ?? 'WAITING')),
                };
            }
        }
        if (! is_array($gate) || (int) ($gate['total'] ?? 0) < 1) {
            return 'WAITING';
        }
        if ((int) ($gate['failed'] ?? 0) > 0) {
            return 'REFER';
        }
        if ((int) ($gate['decided'] ?? 0) >= (int) ($gate['total'] ?? 0)) {
            return 'PASSED';
        }

        return 'WAITING';
    }

    /**
     * After a borrower upload, resume Guided Review on that checklist item —
     * not the first open check on the file.
     *
     * @param  array<string, mixed>  $cursor
     * @return array<string, mixed>
     */
    private function pinUploadedRequest(LoanApplication $application, array $cursor): array
    {
        $ready = $application->documentRequests
            ->filter(fn ($req) => $req->status === 'uploaded' && filled($req->checklist_item))
            ->sortByDesc('id')
            ->first();
        if (! $ready instanceof LoanApplicationDocumentRequest) {
            return $cursor;
        }

        $person = match ((string) ($ready->subject_kind ?? 'borrower')) {
            'member' => 'member',
            'guarantor' => 'guarantor',
            default => 'borrower',
        };
        $subject = $this->checklist->subjectKey(
            $person,
            null,
            $person === 'member' ? (int) $ready->loan_group_member_id : null,
        );
        $verdict = data_get(
            $this->checklist->state($application, $subject),
            'items.'.$ready->checklist_item.'.verdict'
        );
        if (in_array($verdict, ['pass', 'na'], true)) {
            return $cursor;
        }

        $cursor['at_item'] = (string) $ready->checklist_item;
        $cursor['at_person'] = $person;
        if ($person === 'member' && $ready->loan_group_member_id) {
            $cursor['at_m'] = (int) $ready->loan_group_member_id;
        }

        return $cursor;
    }

    private function resumeFromStep(array $step): array
    {
        $participant = $step['participant'] ?? [];

        return array_filter([
            'gate' => $step['gate'] ?? 'income',
            'person' => $participant['person'] ?? 'borrower',
            'm' => $participant['m'] ?? null,
            'g' => $participant['g'] ?? null,
            'item' => $step['item_key'] ?? null,
            'group' => $step['group_key'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $resume
     * @param  array<string, mixed>|null  $waiting
     */
    private function persistQuiet(LoanApplication $application, array $resume, string $bucket, ?array $waiting): void
    {
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        $guided['resume'] = $resume;
        $guided['bucket'] = $bucket;
        if ($waiting) {
            $guided['waiting_kind'] = $waiting['kind'] ?? null;
            $guided['waiting_since'] = $guided['waiting_since'] ?? now()->toIso8601String();
        } else {
            unset($guided['waiting_kind'], $guided['waiting_since']);
        }
        if (($payload['guided'] ?? null) === $guided) {
            return;
        }
        $payload['guided'] = $guided;
        $application->forceFill(['screening_payload' => $payload])->saveQuietly();
    }

    /**
     * Drop the current walk frame and return the previous guided item, or null to leave the wizard.
     *
     * @return array{gate?: string, person?: string, m?: int, g?: int, item?: string, group?: string}|null
     */
    public function popWalk(LoanApplication $application): ?array
    {
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        $walk = array_values((array) ($guided['walk'] ?? []));
        if ($walk === []) {
            return null;
        }
        array_pop($walk);
        $prev = $walk !== [] ? $walk[array_key_last($walk)] : null;
        $guided['walk'] = $walk;
        $payload['guided'] = $guided;
        $application->forceFill(['screening_payload' => $payload])->saveQuietly();

        return is_array($prev) && filled($prev['item'] ?? null) ? $prev : null;
    }

    private function pushWalk(LoanApplication $application, array $step): void
    {
        $pointer = $this->resumeFromStep($step);
        if (! filled($pointer['item'] ?? null)) {
            return;
        }
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        $walk = array_values((array) ($guided['walk'] ?? []));
        $last = $walk !== [] ? $walk[array_key_last($walk)] : null;
        if (is_array($last)
            && ($last['item'] ?? null) === ($pointer['item'] ?? null)
            && ($last['person'] ?? null) === ($pointer['person'] ?? null)
            && (int) ($last['m'] ?? 0) === (int) ($pointer['m'] ?? 0)
            && (int) ($last['g'] ?? 0) === (int) ($pointer['g'] ?? 0)) {
            return;
        }
        $walk[] = $pointer;
        $guided['walk'] = array_slice($walk, -40);
        $payload['guided'] = $guided;
        $application->forceFill(['screening_payload' => $payload])->saveQuietly();
    }

    private function previousWalkHref(LoanApplication $application): ?string
    {
        $walk = array_values((array) data_get($application->screening_payload, 'guided.walk', []));
        if (count($walk) < 2) {
            return null;
        }

        return route('admin.loan-applications.guided-screening', [
            'loan_application' => $application,
            'back' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>|null  $waiting
     */
    private function whatHappensNext(array $step, ?array $waiting, string $ctaKind, int $remaining = 0): string
    {
        if ($waiting) {
            return 'Screening is paused. '.$waiting['detail'].' No action is required from you now.';
        }
        if (($step['type'] ?? '') === 'collateral_secure') {
            return 'Ask the borrower to pledge required collateral on their loan profile. Screening pauses after they are notified.';
        }
        if (($step['type'] ?? '') === 'resolution') {
            return ($step['kind'] ?? '') === 'guarantor'
                ? 'Replace the guarantor. Screening pauses until the borrower provides someone acceptable. Borrower work already completed is kept.'
                : 'Resolve the group composition. Screening pauses until the leader continues with eligible members or replaces enough members.';
        }
        if (($step['type'] ?? '') === 'clarification') {
            return 'Committee asked for clarification. Answer this point, then return the file to Committee.';
        }
        if (($step['type'] ?? '') === 'return_to_committee') {
            return 'Clarification is recorded. Return this file to Committee — they will see what changed.';
        }
        if (($step['type'] ?? '') === 'attention') {
            $detail = $step['recommended']['detail'] ?? $step['prompt'] ?? 'Review this finding.';
            if ($remaining > 0) {
                return $detail.' There are '.$remaining.' checks remaining before Screening can be completed.';
            }

            return $detail;
        }
        if (($step['type'] ?? '') === 'gate_remaining') {
            $gateLeft = (int) ($step['gate_remaining'] ?? 0);
            $overall = (int) ($step['overall_remaining'] ?? $remaining);
            $msg = $gateLeft === 1
                ? 'Finish the remaining check in this gate.'
                : 'Finish the '.$gateLeft.' remaining checks in this gate.';
            if ($overall > $gateLeft) {
                $msg .= ' '.$overall.' checks remain overall — later gates stay locked until this gate is done.';
            }

            return $msg;
        }
        if (($step['type'] ?? '') === 'gate_complete') {
            return $remaining > 0
                ? 'Open the next unresolved check. '.$remaining.' requirements remain overall.'
                : 'Continue with the remaining Screening checks.';
        }
        if (($step['type'] ?? '') === 'gate_2_passed') {
            return '';
        }
        if (($step['type'] ?? '') === 'gate_3_passed') {
            return '';
        }
        if ($ctaKind === 'decision' && ($step['type'] ?? '') === 'decision') {
            return 'Screening is complete. Continue to Decision when you are ready to record the recommendation.';
        }
        $name = $step['participant']['name'] ?? $step['participant']['label'] ?? 'this participant';
        $title = strtolower((string) ($step['title'] ?? 'this check'));

        return 'Verify '.$title.' for '.$name.'. After this is recorded, you continue with the remaining checks.';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $groupReview
     * @return array<string, mixed>
     */
    private function resolutionStep(LoanApplication $application, array $snapshot, array $groupReview): array
    {
        $resolution = (array) ($snapshot['policy']['resolution'] ?? []);
        $code = (string) ($resolution['code'] ?? '');
        $kind = str_contains($code, 'guarantor') ? 'guarantor' : 'group';
        $base = $this->gateOneStep($application, $snapshot, $groupReview);
        $link = $this->failedGuarantorLink($application, $resolution);
        $memberIds = $this->failedMemberIds($application, $resolution);

        $customerReason = $kind === 'guarantor'
            ? "The guarantor's current financial commitments do not provide enough capacity for this guarantee."
            : (string) ($resolution['detail'] ?? 'One or more members do not meet the requirements for this loan.');

        return array_merge($base, [
            'type' => 'resolution',
            'title' => $kind === 'guarantor'
                ? 'Guarantor does not meet the required policy'
                : (string) ($resolution['next_action'] ?? 'Group eligibility'),
            'prompt' => (string) ($resolution['detail'] ?? ''),
            'customer_reason' => $customerReason,
            'kind' => $kind,
            'allow_continue' => ! empty($resolution['allow_continue_without_failed']),
            'continue_cta' => $resolution['continue_cta'] ?? null,
            'replace_cta' => $resolution['cta'] ?? ($kind === 'guarantor' ? 'Replace guarantor' : 'Replace member'),
            'guarantor_link_id' => $link?->id,
            'member_ids' => $memberIds,
            'eligible' => $resolution['current_eligible_members'] ?? null,
            'minimum' => $resolution['minimum_eligible_members'] ?? null,
            'recommended' => [
                'label' => 'Required next step',
                'detail' => $kind === 'guarantor'
                    ? 'Replace guarantor'
                    : (string) ($resolution['cta'] ?? 'Resolve group members'),
            ],
            'primary' => $kind === 'guarantor' ? 'Replace guarantor & pause' : 'Request member replacement & pause',
        ]);
    }

    /**
     * @param  array<string, mixed>  $clarification
     * @return array<string, mixed>
     */
    private function clarificationStep(array $clarification): array
    {
        $answered = filled($clarification['response'] ?? null);

        return [
            'type' => $answered ? 'return_to_committee' : 'clarification',
            'gate' => $clarification['gate'] ?? 'final',
            'gate_index' => 6,
            'gate_label' => 'Committee clarification',
            'title' => $answered ? 'Return to Committee' : 'Committee clarification',
            'prompt' => (string) ($clarification['question'] ?? 'Committee asked for clarification.'),
            'response' => $clarification['response'] ?? null,
            'primary' => $answered ? 'Return to Committee' : 'Save & return',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function unresolvedClarification(LoanApplication $application): ?array
    {
        $row = data_get($application->screening_payload, 'guided.committee_clarification');
        if (! is_array($row) || ! empty($row['returned_at'])) {
            return null;
        }

        return $row;
    }

    private function hasLaterHumanWork(LoanApplication $application): bool
    {
        $bySubject = data_get($application->screening_payload, 'screening_checklist.by_subject', []);
        if (! is_array($bySubject)) {
            return false;
        }
        foreach ($bySubject as $subject) {
            foreach ((array) ($subject['items'] ?? []) as $item) {
                if (in_array($item['verdict'] ?? null, ['pass', 'fail', 'na'], true)
                    && ($item['source'] ?? '') !== 'system') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $resolution
     */
    private function failedGuarantorLink(LoanApplication $application, array $resolution): ?CustomerGuarantor
    {
        $failedG = collect($resolution['failed_guarantors'] ?? [])->first();
        $links = $application->customerGuarantors ?? collect();

        return $links->first(function ($link) use ($failedG) {
            if (! is_array($failedG)) {
                return ! in_array((string) ($link->status ?? ''), ['replaced', 'declined', 'cancelled'], true);
            }
            $cid = (int) ($failedG['customer_id'] ?? 0);
            $inviteCid = (int) ($link->invitation?->guarantor_customer_id ?? 0);

            return $cid > 0 && $inviteCid === $cid;
        }) ?? $links->first(fn ($link) => ! in_array((string) ($link->status ?? ''), ['replaced', 'declined', 'cancelled'], true));
    }

    /**
     * @param  array<string, mixed>  $resolution
     * @return list<int>
     */
    private function failedMemberIds(LoanApplication $application, array $resolution): array
    {
        $failedIds = collect($resolution['failed_members'] ?? [])
            ->pluck('customer_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($failedIds === []) {
            return [];
        }

        $members = $application->loanGroup?->members;
        if (! $members) {
            return [];
        }

        return $members
            ->filter(fn ($m) => in_array((int) $m->customer_id, $failedIds, true) && ! $m->isLeader())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function markGateSeen(LoanApplication $application, string $gate): void
    {
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        $seen = (array) ($guided['seen_gates'] ?? []);
        $seen[$gate] = now()->toIso8601String();
        $guided['seen_gates'] = $seen;
        $guided['last_activity_at'] = now()->toIso8601String();
        $payload['guided'] = $guided;
        $application->update(['screening_payload' => $payload]);
    }

    public function saveCommitteeClarification(LoanApplication $application, string $response): void
    {
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        $row = (array) ($guided['committee_clarification'] ?? []);
        $row['response'] = $response;
        $row['answered_at'] = now()->toIso8601String();
        $guided['committee_clarification'] = $row;
        $guided['last_activity_at'] = now()->toIso8601String();
        $payload['guided'] = $guided;
        $application->update(['screening_payload' => $payload]);
    }

    public function returnClarificationToCommittee(LoanApplication $application): void
    {
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        $row = (array) ($guided['committee_clarification'] ?? []);
        $row['returned_at'] = now()->toIso8601String();
        $guided['committee_clarification'] = $row;
        $payload['guided'] = $guided;
        $application->update([
            'screening_payload' => $payload,
            'current_stage' => 'pre_approval',
        ]);
    }

    public function lastActivityLabel(LoanApplication $application): ?string
    {
        $raw = data_get($application->screening_payload, 'guided.last_activity_at');
        if (! filled($raw)) {
            return null;
        }
        try {
            return Carbon::parse($raw)->diffForHumans();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @return list<array<string, mixed>>
     */
    private function orderedSubjects(array $subjects): array
    {
        $focusPerson = request('focus_person');
        $focusM = request()->filled('focus_m') ? (int) request('focus_m') : null;
        $focusG = request()->filled('focus_g') ? (int) request('focus_g') : null;
        if (! filled($focusPerson)) {
            return $subjects;
        }

        return collect($subjects)
            ->sortBy(fn ($subject) => $this->sameSubject($subject, (string) $focusPerson, $focusM, $focusG) ? 0 : 1)
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @param  array<string, mixed>  $subject
     */
    private function subjectIndex(array $subjects, array $subject): int
    {
        foreach ($subjects as $index => $row) {
            if ($this->sameSubject(
                $row,
                (string) ($subject['person'] ?? 'borrower'),
                isset($subject['m']) ? (int) $subject['m'] : null,
                isset($subject['g']) ? (int) $subject['g'] : null,
            )) {
                return $index + 1;
            }
        }

        return 1;
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function sameSubject(array $subject, string $person, ?int $m, ?int $g): bool
    {
        if ((string) ($subject['person'] ?? 'borrower') !== $person) {
            return false;
        }
        if ($m !== null && (int) ($subject['m'] ?? 0) !== $m) {
            return false;
        }
        if ($g !== null && (int) ($subject['g'] ?? 0) !== $g) {
            return false;
        }

        return true;
    }
}
