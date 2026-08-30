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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forApplication(LoanApplication $application, ?User $actor = null): array
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

        $step = match (true) {
            $clarification !== null => $this->clarificationStep($clarification),
            $waiting !== null => $this->waitingStep($waiting, $snapshot),
            $needsResolutionUi => $this->resolutionStep($application, $snapshot, $groupReview),
            default => $this->firstActionableStep($application, $actor, $review, $groupReview, $subjects, $snapshot),
        };

        $started = filled(data_get($application->screening_payload, 'guided.started_at'));
        $stage = (string) $application->current_stage;
        $screeningOpen = in_array($stage, ['submitted', 'screening', 'credit_appraisal'], true);
        $decisionReady = (bool) ($snapshot['decision_unlocked'] ?? false);
        $bucket = match (true) {
            ! $screeningOpen || $decisionReady => self::BUCKET_COMPLETED,
            $waiting !== null => self::BUCKET_WAITING,
            default => self::BUCKET_DO_NOW,
        };

        $ctaKind = match (true) {
            $bucket === self::BUCKET_COMPLETED => 'decision',
            $waiting !== null => 'waiting',
            $started => 'continue',
            default => 'start',
        };
        $cta = match ($ctaKind) {
            'decision' => 'Continue to Decision',
            'waiting' => (string) ($waiting['label'] ?? 'Waiting'),
            'continue' => 'Continue Screening',
            default => 'Start Screening',
        };

        $resume = $this->resumeFromStep($step);
        $this->persistQuiet($application, $resume, $bucket, $waiting);

        $href = $ctaKind === 'decision'
            ? route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'decision',
            ]).'#review-recommendation'
            : route('admin.loan-applications.guided-screening', array_filter([
                'loan_application' => $application,
                'gate' => $resume['gate'] ?? null,
                'person' => $resume['person'] ?? null,
                'm' => $resume['m'] ?? null,
                'g' => $resume['g'] ?? null,
                'open_item' => $resume['item'] ?? null,
            ]));

        return [
            'cta' => $cta,
            'cta_kind' => $ctaKind,
            'resumable' => $ctaKind === 'continue' || $ctaKind === 'start',
            'href' => $href,
            'checklist_href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'gate' => $resume['gate'] ?? 'income',
                'review_person' => $resume['person'] ?? 'borrower',
                'review_m' => $resume['m'] ?? null,
                'review_g' => $resume['g'] ?? null,
                'open_item' => $resume['item'] ?? null,
            ]).'#review-desk',
            'bucket' => $bucket,
            'waiting' => $waiting,
            'started' => $started,
            'last_activity_at' => data_get($application->screening_payload, 'guided.last_activity_at'),
            'gate' => $resume['gate'] ?? ($step['gate'] ?? 'income'),
            'gate_index' => (int) ($step['gate_index'] ?? 1),
            'gate_total' => 6,
            'participant' => $step['participant'] ?? null,
            'step' => $step,
            'what_happens_next' => $this->whatHappensNext($step, $waiting, $ctaKind),
            'recommended' => $step['recommended'] ?? null,
            'sequence' => $snapshot,
            'subjects' => collect($subjects)->map(fn ($s) => [
                'label' => $s['label'] ?? null,
                'person' => $s['person'] ?? null,
                'm' => $s['m'] ?? null,
                'g' => $s['g'] ?? null,
                'customer_id' => $s['customer_id'] ?? null,
                'href' => route('admin.loan-applications.guided-screening', array_filter([
                    'loan_application' => $application,
                    'focus_person' => $s['person'] ?? null,
                    'focus_m' => $s['m'] ?? null,
                    'focus_g' => $s['g'] ?? null,
                ])),
            ])->all(),
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
    ): array {
        if ($snapshot['pending_rejection'] ?? false) {
            return $this->gateOneStep($application, $snapshot, $groupReview);
        }

        if (! ($snapshot['declared']['pass'] ?? false)) {
            return $this->gateOneStep($application, $snapshot, $groupReview);
        }

        $gate1Seen = (bool) data_get($application->screening_payload, 'guided.seen_gates.declared');
        if (! $gate1Seen && ! $this->hasLaterHumanWork($application)) {
            return $this->gateOneStep($application, $snapshot, $groupReview);
        }

        $subjectTotal = max(1, count($subjects));
        foreach ($this->orderedSubjects($subjects) as $subject) {
            $subjectIndex = $this->subjectIndex($subjects, $subject);
            $person = (string) ($subject['person'] ?? 'borrower');
            $m = isset($subject['m']) ? (int) $subject['m'] : null;
            $g = isset($subject['g']) ? (int) $subject['g'] : null;
            $desk = $this->checklist->deskViewModel($application, $review, $groupReview, $actor, $person, $g, $m);
            $gates = $this->gates->regroup($desk['groups'] ?? [], $application);
            $customer = $this->subjectCustomer($application, $review, $subject);
            $card = $this->checklist->identityPeopleCard($desk, $customer);

            foreach (['income', 'crb', 'identity', 'collateral', 'final'] as $gateKey) {
                $gate = $gates[$gateKey] ?? null;
                if (! is_array($gate) || ! empty($gate['locked'])) {
                    continue;
                }
                foreach ($gate['groups'] ?? [] as $group) {
                    foreach ($group['items'] ?? [] as $item) {
                        if ($this->gates->isQuietAuto($item)) {
                            continue;
                        }
                        $humanOpen = ($item['verdict'] ?? null) === null && $this->gates->isHumanWork($item);
                        $needsResolution = $this->gates->isSystemFail($item)
                            || ! empty($item['awaiting_data']);
                        if (! $humanOpen && ! $needsResolution) {
                            continue;
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

        if ($snapshot['decision_unlocked'] ?? false) {
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

        return $this->gateCompleteStep($snapshot);
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
        $index = match ($gateKey) {
            'income' => 2,
            'crb' => 3,
            'identity' => 4,
            'collateral' => 5,
            default => 6,
        };
        $requestable = $this->requestablePreset($key, $item);
        $contact = $this->contactContext($key, $card, $customer);
        $isRequest = $requestable && (($item['verdict'] ?? null) === 'fail' || ! empty($item['awaiting_data']));

        return [
            'type' => $isRequest ? 'request' : 'human',
            'recommended' => $isRequest ? [
                'label' => 'Required next step',
                'detail' => $requestable['label'] ?? 'Request the missing evidence',
            ] : null,
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
            'requestable' => $requestable,
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
            'primary' => $requestable ? 'Request & pause' : 'Save & Next',
            'outcomes' => $this->outcomesFor($key),
            'evidence' => $item['evidence'] ?? [],
        ];
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
            'gate_index' => (int) ($waiting['gate_index'] ?? 4),
            'gate_label' => $waiting['label'] ?? 'Waiting',
            'title' => $waiting['label'] ?? 'Screening paused',
            'prompt' => $waiting['detail'] ?? 'We will continue from this step when the required information is received.',
            'waiting' => $waiting,
            'primary' => null,
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

        if ($snapshot['pending_rejection'] ?? false) {
            return [
                'kind' => 'policy',
                'label' => 'Pending automatic rejection',
                'detail' => (string) ($snapshot['remaining_label'] ?? ''),
                'gate' => 'declared',
                'gate_index' => 1,
                'since' => null,
            ];
        }

        $open = $application->documentRequests
            ->filter(fn ($req) => $this->documents->isOutstanding($req))
            ->first();
        if ($open instanceof LoanApplicationDocumentRequest) {
            return [
                'kind' => 'document',
                'label' => 'Waiting for document',
                'detail' => $open->label.' · '.$this->documents->waitingOnLabel($open, $groupReview),
                'gate' => $this->documents->borrowerActionKind($open) === 'collateral' ? 'collateral' : 'identity',
                'gate_index' => $this->documents->borrowerActionKind($open) === 'collateral' ? 5 : 4,
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
     * @param  array<string, mixed>  $item
     * @return array{label: string, preset: string}|null
     */
    private function requestablePreset(string $key, array $item): ?array
    {
        $code = (string) ($item['fail_reason_code'] ?? '');
        if (in_array($key, ['identity.nida_vs_dob'], true)
            || in_array($code, ['nida_missing', 'nida_malformed', 'nida_incomplete'], true)) {
            return ['label' => 'Request National ID', 'preset' => 'Updated National ID'];
        }
        if ($key === 'identity.face_vs_nida' || in_array($code, ['face_photo_missing', 'photos_missing'], true)) {
            return ['label' => 'Request face photo', 'preset' => 'Image Not Clear'];
        }
        if (in_array($code, ['id_photo_missing'], true)) {
            return ['label' => 'Request ID photo', 'preset' => 'New National ID photo'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>|null
     */
    private function contactContext(string $key, array $card, ?Customer $customer): ?array
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
                'detail' => $customer->national_id,
                'phone' => $customer->phone,
            ] : null,
        };
    }

    /** @return list<array{value: string, label: string, fail_reason_code?: string}> */
    private function outcomesFor(string $key): array
    {
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

    private function defaultPrompt(string $key): string
    {
        return match (true) {
            str_contains($key, 'next_of_kin') => 'Were you able to confirm the next-of-kin information?',
            str_contains($key, 'local_government') => 'Were you able to verify the Local Government Officer?',
            str_contains($key, 'spouse') => 'Were you able to confirm the spouse details?',
            str_contains($key, 'activity_plausible') => 'Does the financial activity support the declared income?',
            str_contains($key, 'bank_or_mobile') => 'Are there material patterns that need attention?',
            default => 'Record the result for this check.',
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
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
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
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>|null  $waiting
     */
    private function whatHappensNext(array $step, ?array $waiting, string $ctaKind): string
    {
        if ($waiting) {
            return 'Screening is paused. '.$waiting['detail'].' No action is required from you now.';
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
        if ($ctaKind === 'decision') {
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
