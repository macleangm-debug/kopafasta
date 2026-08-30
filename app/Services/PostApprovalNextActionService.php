<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\PartnerTask;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Canonical next action for Guided Post-Approval.
 * Wraps existing disbursement readiness, asset reservation, and partner tasks.
 * Does not create a second credit or disbursement engine.
 */
class PostApprovalNextActionService
{
    public const BUCKET_DO_NOW = 'do_now';

    public const BUCKET_WAITING = 'waiting';

    public const BUCKET_READY = 'ready';

    public const BUCKET_COMPLETED = 'completed';

    public const TIMING_BEFORE_CONTRACT = 'before_contract';

    public const TIMING_BEFORE_DISBURSEMENT = 'before_disbursement';

    public function __construct(
        private readonly ApplicationDisbursementReadinessService $readiness,
        private readonly UnderwritingSettingsService $settings,
        private readonly CreditAuthorityService $authority,
    ) {}

    /**
     * Staff-facing timing labels. Never show enum names in Settings UI.
     *
     * @return array<string, string>
     */
    public static function timingLabels(): array
    {
        return [
            self::TIMING_BEFORE_CONTRACT => 'Must be completed before agreement',
            self::TIMING_BEFORE_DISBURSEMENT => 'Must be completed before disbursement',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function appliesLabels(): array
    {
        return [
            'all' => 'All products',
            'cash' => 'Cash facilities',
            'asset' => 'Asset-backed facilities',
            'secured' => 'Facilities with pledged collateral',
            'gps' => 'Products/assets that require GPS',
            'insurance' => 'Products/assets that require insurance',
            'group' => 'Group loans',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function partyLabels(): array
    {
        return [
            'customer' => 'Customer',
            'management' => 'Credit management',
            'gps_partner' => 'GPS partner',
            'insurance_partner' => 'Insurance partner',
        ];
    }

    /**
     * Default catalog. Settings Hub overlays saved rows by key.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultCatalog(): array
    {
        return [
            [
                'key' => 'offer_accepted',
                'label' => 'Offer accepted',
                'required' => true,
                'applies_to' => 'all',
                'responsible_party' => 'customer',
                'timing' => self::TIMING_BEFORE_CONTRACT,
                'deadline_days' => null,
                'blocking' => true,
                'customer_reminders' => true,
                'locked' => true,
            ],
            [
                'key' => 'post_approval_fees',
                'label' => 'Post-approval fees',
                'required' => true,
                'applies_to' => 'all',
                'responsible_party' => 'customer',
                'timing' => self::TIMING_BEFORE_CONTRACT,
                'deadline_days' => null,
                'blocking' => true,
                'customer_reminders' => true,
            ],
            [
                'key' => 'ownership_transfer',
                'label' => 'Asset ownership / registration',
                'required' => true,
                'applies_to' => 'asset',
                'responsible_party' => 'management',
                'timing' => self::TIMING_BEFORE_CONTRACT,
                'deadline_days' => null,
                'blocking' => true,
                'customer_reminders' => false,
            ],
            [
                'key' => 'gps_installation',
                'label' => 'GPS installation',
                'required' => true,
                'applies_to' => 'gps',
                'responsible_party' => 'gps_partner',
                'timing' => self::TIMING_BEFORE_DISBURSEMENT,
                'deadline_days' => null,
                'blocking' => true,
                'customer_reminders' => false,
            ],
            [
                'key' => 'insurance',
                'label' => 'Insurance',
                'required' => true,
                'applies_to' => 'insurance',
                'responsible_party' => 'insurance_partner',
                'timing' => self::TIMING_BEFORE_DISBURSEMENT,
                'deadline_days' => null,
                'blocking' => true,
                'customer_reminders' => true,
            ],
            [
                'key' => 'destination_verification',
                'label' => 'Payout destination verified',
                'required' => true,
                'applies_to' => 'cash',
                'responsible_party' => 'customer',
                'timing' => self::TIMING_BEFORE_DISBURSEMENT,
                'deadline_days' => null,
                'blocking' => true,
                'customer_reminders' => true,
            ],
            [
                'key' => 'contract_executed',
                'label' => 'Loan agreement signed',
                'required' => true,
                'applies_to' => 'all',
                'responsible_party' => 'customer',
                'timing' => self::TIMING_BEFORE_DISBURSEMENT,
                'deadline_days' => null,
                'blocking' => true,
                'customer_reminders' => true,
                'locked' => true,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        $saved = $this->settings->postApprovalConditionRows();
        $byKey = collect($saved)->keyBy(fn ($row) => (string) ($row['key'] ?? ''));

        return collect(self::defaultCatalog())->map(function (array $row) use ($byKey) {
            $overlay = $byKey->get($row['key']);
            if (! is_array($overlay)) {
                return $row;
            }
            $merged = array_merge($row, $overlay);
            $merged['key'] = $row['key'];
            if (! empty($row['locked'])) {
                $merged['required'] = true;
                $merged['blocking'] = true;
                $merged['locked'] = true;
            }
            $merged['timing'] = in_array($merged['timing'] ?? '', [
                self::TIMING_BEFORE_CONTRACT,
                self::TIMING_BEFORE_DISBURSEMENT,
            ], true) ? $merged['timing'] : $row['timing'];
            $merged['applies_to'] = array_key_exists($merged['applies_to'] ?? '', self::appliesLabels())
                ? $merged['applies_to']
                : $row['applies_to'];
            $merged['responsible_party'] = array_key_exists($merged['responsible_party'] ?? '', self::partyLabels())
                ? $merged['responsible_party']
                : $row['responsible_party'];
            $merged['required'] = (bool) ($merged['required'] ?? $row['required']);
            $merged['blocking'] = (bool) ($merged['blocking'] ?? $row['blocking']);
            $merged['customer_reminders'] = (bool) ($merged['customer_reminders'] ?? $row['customer_reminders']);
            $merged['deadline_days'] = isset($merged['deadline_days']) && $merged['deadline_days'] !== '' && $merged['deadline_days'] !== null
                ? max(0, (int) $merged['deadline_days'])
                : null;

            return $merged;
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function forApplication(LoanApplication $application, ?User $actor = null): array
    {
        unset($actor);
        $application->loadMissing(['customer', 'product', 'loan', 'loanGroup', 'collateralAssets', 'postApprovalFees']);

        if ((string) $application->current_stage === 'awaiting_management') {
            return $this->leftoverAuthorization($application);
        }

        $conditions = $this->evaluateConditions($application);
        $next = collect($conditions)->first(
            fn ($row) => ! empty($row['required']) && ! empty($row['blocking']) && empty($row['complete'])
        );
        $contract = $this->contractReadiness($application, $conditions);
        $disbursement = $this->disbursementReadiness($application, $conditions);
        $loanActive = $application->loan && in_array((string) $application->loan->status, ['active', 'arrears', 'disbursed'], true);
        $waiting = is_array($next) && ($next['waiting'] ?? false);
        $started = filled(data_get($application->screening_payload, 'guided.post_approval_opened_at'));

        $bucket = match (true) {
            $loanActive || (string) $application->status === 'disbursed' => self::BUCKET_COMPLETED,
            $disbursement['ready'] => self::BUCKET_READY,
            $waiting => self::BUCKET_WAITING,
            default => self::BUCKET_DO_NOW,
        };

        $cta = match (true) {
            $bucket === self::BUCKET_READY => 'Review disbursement',
            $waiting => 'Waiting · '.($next['label'] ?? 'condition'),
            $started => 'Continue Post-Approval',
            default => 'Start Post-Approval',
        };

        $href = $bucket === self::BUCKET_READY
            ? route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ]).'#credit-management-desk'
            : route('admin.loan-applications.guided-post-approval', $application);

        return [
            'bucket' => $bucket,
            'cta' => $cta,
            'cta_kind' => match ($bucket) {
                self::BUCKET_READY => 'disburse',
                self::BUCKET_WAITING => 'waiting',
                self::BUCKET_COMPLETED => 'completed',
                default => $started ? 'continue' : 'start',
            },
            'href' => $href,
            'desk_href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ]).($next['fragment'] ?? '#credit-management-desk'),
            'condition' => $next,
            'conditions' => $conditions,
            'checklist' => $this->checklistFromConditions($conditions),
            'waiting' => $waiting,
            'waiting_on' => $next['waiting_on'] ?? null,
            'blocking_reason' => $next['blocking_reason'] ?? null,
            'next_action' => $next['next_action'] ?? ($disbursement['ready'] ? 'Review disbursement' : 'Continue Post-Approval'),
            'destination' => $next['destination'] ?? $href,
            'contract_ready' => $contract['ready'],
            'contract_readiness' => $contract,
            'disbursement_ready' => $disbursement['ready'],
            'disbursement_readiness' => $disbursement,
            'authority_reason' => $this->authority->managementRequirementReason($application),
            'what_happens_next' => $this->whatHappensNext($bucket, $next, $contract, $disbursement),
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $conditions
     * @return array{ready: bool, remaining: list<array<string, mixed>>, remaining_count: int, headline: string, detail: ?string}
     */
    public function contractReadiness(LoanApplication $application, ?array $conditions = null): array
    {
        $conditions ??= $this->evaluateConditions($application);
        $remaining = collect($conditions)
            ->filter(fn ($row) => ($row['timing'] ?? '') === self::TIMING_BEFORE_CONTRACT
                && ! empty($row['required'])
                && ! empty($row['applies'])
                && empty($row['complete']))
            ->values()
            ->all();

        $count = count($remaining);
        $ready = $count === 0;
        $first = $remaining[0] ?? null;

        return [
            'ready' => $ready,
            'remaining' => $remaining,
            'remaining_count' => $count,
            'headline' => $ready ? 'Agreement ready' : 'Agreement not ready',
            'detail' => $ready
                ? 'Every required before-agreement condition is satisfied.'
                : ($count === 1
                    ? '1 requirement remains: '.($first['label'] ?? 'condition')
                    : $count.' requirements remain. Next: '.($first['label'] ?? 'condition')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $conditions
     * @return array{ready: bool, remaining: list<array<string, mixed>>}
     */
    public function disbursementReadiness(LoanApplication $application, ?array $conditions = null): array
    {
        $conditions ??= $this->evaluateConditions($application);
        $remaining = collect($conditions)
            ->filter(fn ($row) => ! empty($row['required'])
                && ! empty($row['blocking'])
                && ! empty($row['applies'])
                && empty($row['complete']))
            ->values()
            ->all();

        return [
            'ready' => $remaining === [] && $this->readiness->canMarkDisbursement($application),
            'remaining' => $remaining,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function evaluateConditions(LoanApplication $application): array
    {
        $index = 0;

        return collect($this->catalog())->map(function (array $row) use ($application, &$index) {
            $applies = $this->applies($application, (string) $row['applies_to']);
            $complete = $applies ? $this->isComplete($application, (string) $row['key']) : true;
            $waitingOn = null;
            $waiting = false;
            $blockingReason = null;
            if ($applies && ! $complete) {
                [$waiting, $waitingOn, $blockingReason] = $this->waitingFor($application, $row);
            }
            $index++;

            return array_merge($row, [
                'index' => $index,
                'applies' => $applies,
                'complete' => $complete,
                'waiting' => $waiting,
                'waiting_on' => $waitingOn,
                'blocking_reason' => $blockingReason,
                'timing_label' => self::timingLabels()[$row['timing']] ?? $row['timing'],
                'party_label' => self::partyLabels()[$row['responsible_party']] ?? $row['responsible_party'],
                'applies_label' => self::appliesLabels()[$row['applies_to']] ?? $row['applies_to'],
                'status' => ! $applies
                    ? 'not_required'
                    : ($complete ? 'complete' : ($waiting ? 'pending' : 'open')),
                'next_action' => $complete || ! $applies
                    ? null
                    : ($waiting
                        ? 'Waiting on '.(self::partyLabels()[$waitingOn ?? ''] ?? ($waitingOn ?? 'an external party'))
                        : 'Do now · '.$row['label']),
                'destination' => $this->destination($application, (string) $row['key'], $complete, $applies),
                'fragment' => match ($row['key']) {
                    'gps_installation' => '#asset-lending',
                    'insurance' => '#asset-lending',
                    'ownership_transfer' => '#asset-lending',
                    default => '#credit-management-desk',
                },
            ]);
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $conditions
     * @return array<string, array{label: string, status: string, complete: bool}>
     */
    private function checklistFromConditions(array $conditions): array
    {
        $out = [];
        foreach ($conditions as $row) {
            if (empty($row['applies']) && empty($row['required'])) {
                continue;
            }
            $out[(string) $row['key']] = [
                'label' => $row['label'],
                'status' => $row['status'],
                'complete' => ! empty($row['complete']) || empty($row['applies']),
            ];
        }

        return $out;
    }

    private function leftoverAuthorization(LoanApplication $application): array
    {
        $href = route('admin.loan-applications.show', [
            'loan_application' => $application,
            'workspace' => 'decision',
        ]).'#review-action-zone';

        return [
            'bucket' => self::BUCKET_DO_NOW,
            'cta' => 'Complete leftover authorization',
            'cta_kind' => 'leftover',
            'href' => $href,
            'desk_href' => $href,
            'condition' => [
                'key' => 'leftover_authorization',
                'label' => 'Issue Offer',
                'complete' => false,
                'waiting' => false,
            ],
            'conditions' => [],
            'checklist' => [],
            'waiting' => false,
            'waiting_on' => null,
            'blocking_reason' => 'This file is still in the former authorization queue.',
            'next_action' => 'Issue the Offer, then continue Post-Approval.',
            'destination' => $href,
            'contract_ready' => false,
            'contract_readiness' => ['ready' => false, 'remaining' => [], 'remaining_count' => 1, 'headline' => 'Agreement not ready', 'detail' => 'Issue the Offer first.'],
            'disbursement_ready' => false,
            'disbursement_readiness' => ['ready' => false, 'remaining' => []],
            'authority_reason' => $this->authority->managementRequirementReason($application),
            'what_happens_next' => 'Committee already decided. Issue the Offer from this leftover queue, then run Post-Approval. Do not re-underwrite.',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: bool, 1: ?string, 2: ?string}
     */
    private function waitingFor(LoanApplication $application, array $row): array
    {
        $party = (string) ($row['responsible_party'] ?? 'management');
        $key = (string) ($row['key'] ?? '');

        if ($key === 'gps_installation') {
            $open = $this->openGpsTask($application);
            if ($open) {
                return [true, 'gps_partner', 'Waiting for GPS Partner'];
            }

            return [false, 'management', 'Assign the GPS partner to continue.'];
        }

        if ($key === 'contract_executed') {
            $contract = $this->readiness->loanContract($application);
            if (! $contract) {
                return [false, 'management', 'Generate the agreement.'];
            }
            if (! $this->readiness->contractSigned($application)) {
                return [true, 'customer', 'Waiting for customer to sign the agreement.'];
            }
        }

        if ($party === 'customer') {
            return [true, 'customer', 'Waiting for customer.'];
        }

        if ($party === 'insurance_partner') {
            return [true, 'insurance_partner', 'Waiting for insurance partner.'];
        }

        return [false, $party, $row['label'].' still needs Credit management.'];
    }

    private function applies(LoanApplication $application, string $appliesTo): bool
    {
        return match ($appliesTo) {
            'all' => true,
            'cash' => ! $this->readiness->isAssetLendingApplication($application),
            'asset' => $this->readiness->isAssetLendingApplication($application),
            'secured' => $this->readiness->isAssetLendingApplication($application)
                || $application->collateralAssets->isNotEmpty(),
            'gps' => $this->gpsRequired($application),
            'insurance' => $this->insuranceRequired($application),
            'group' => (bool) $application->loan_group_id,
            default => false,
        };
    }

    private function isComplete(LoanApplication $application, string $key): bool
    {
        return match ($key) {
            'offer_accepted' => $this->readiness->offerSigned($application),
            'post_approval_fees' => ! $this->readiness->hasPostApprovalFees($application)
                || $this->readiness->feesPaid($application),
            'ownership_transfer' => $this->ownershipComplete($application),
            'gps_installation' => $this->gpsComplete($application),
            'insurance' => $this->insuranceComplete($application),
            'destination_verification' => $this->readiness->isAssetLendingApplication($application)
                || $this->readiness->disbursementDetailsConfirmed($application),
            'contract_executed' => $this->readiness->contractSigned($application),
            default => true,
        };
    }

    private function gpsRequired(LoanApplication $application): bool
    {
        if ($application->collateralAssets->contains(fn ($row) => (bool) ($row->gps_required ?? false))) {
            return true;
        }

        $reservation = app(AssetReservationService::class)->reservationForApplication($application);
        if (! $reservation) {
            return false;
        }
        $reqs = app(AssetLendingService::class)->categoryRequirements($reservation->asset?->category);

        return (bool) ($reqs['gps_required'] ?? false);
    }

    private function insuranceRequired(LoanApplication $application): bool
    {
        $reservation = app(AssetReservationService::class)->reservationForApplication($application);
        if (! $reservation) {
            return false;
        }
        $reqs = app(AssetLendingService::class)->categoryRequirements($reservation->asset?->category);

        return (bool) ($reqs['insurance_required'] ?? false);
    }

    private function ownershipComplete(LoanApplication $application): bool
    {
        $reservation = app(AssetReservationService::class)->reservationForApplication($application);
        if (! $reservation) {
            return true;
        }
        $reqs = app(AssetLendingService::class)->categoryRequirements($reservation->asset?->category);
        if (! ($reqs['ownership_transfer_required'] ?? false)) {
            return true;
        }

        return in_array((string) $reservation->status, ['registration_complete', 'released'], true);
    }

    private function gpsComplete(LoanApplication $application): bool
    {
        if (in_array('gps_installed', $application->borrower_completed_steps ?? [], true)) {
            return true;
        }

        $done = PartnerTask::query()
            ->where('loan_application_id', $application->id)
            ->where('task_type', 'gps_install')
            ->where('status', 'completed')
            ->exists();
        if ($done) {
            return true;
        }

        $reservation = app(AssetReservationService::class)->reservationForApplication($application);
        $status = (string) ($reservation?->status ?? '');

        return in_array($status, ['insurance_active', 'registration_complete', 'released'], true);
    }

    private function insuranceComplete(LoanApplication $application): bool
    {
        if (in_array('insurance_active', $application->borrower_completed_steps ?? [], true)) {
            return true;
        }

        $reservation = app(AssetReservationService::class)->reservationForApplication($application);
        $status = (string) ($reservation?->status ?? '');

        return in_array($status, ['insurance_active', 'registration_complete', 'released'], true);
    }

    private function openGpsTask(LoanApplication $application): ?PartnerTask
    {
        return PartnerTask::query()
            ->where('loan_application_id', $application->id)
            ->where('task_type', 'gps_install')
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
            ->latest('id')
            ->first();
    }

    private function destination(LoanApplication $application, string $key, bool $complete, bool $applies): string
    {
        if (! $applies || $complete) {
            return route('admin.loan-applications.guided-post-approval', $application);
        }

        return match ($key) {
            'contract_executed' => route('admin.loan-applications.guided-post-approval', $application),
            'gps_installation', 'insurance', 'ownership_transfer' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ]).'#asset-lending',
            default => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ]).'#credit-management-desk',
        };
    }

    /**
     * @param  array<string, mixed>|null  $next
     * @param  array<string, mixed>  $contract
     * @param  array<string, mixed>  $disbursement
     */
    private function whatHappensNext(string $bucket, ?array $next, array $contract, array $disbursement): string
    {
        if ($bucket === self::BUCKET_COMPLETED) {
            return 'This facility is already active.';
        }
        if ($bucket === self::BUCKET_READY) {
            return 'All disbursement conditions are satisfied. Review disbursement — signing a contract does not activate the loan.';
        }
        if (is_array($next) && ! empty($next['waiting'])) {
            return ($next['blocking_reason'] ?? 'Waiting on an external party').' The file returns to Do now when that condition is satisfied.';
        }
        if (is_array($next) && ($next['key'] ?? '') === 'contract_executed') {
            return $contract['ready']
                ? 'Agreement ready. Generate the agreement, then collect signatures.'
                : (string) ($contract['detail'] ?? 'Agreement not ready.');
        }
        if (is_array($next)) {
            return 'Next condition: '.($next['label'] ?? 'Continue post-approval').'.';
        }
        if ($disbursement['ready'] ?? false) {
            return 'Ready for disbursement.';
        }

        return 'Continue Post-Approval one condition at a time.';
    }

    public function markOpened(LoanApplication $application): void
    {
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        if (empty($guided['post_approval_opened_at'])) {
            $guided['post_approval_opened_at'] = now()->toIso8601String();
        }
        $guided['post_approval_last_activity_at'] = now()->toIso8601String();
        $payload['guided'] = $guided;
        $application->forceFill(['screening_payload' => $payload])->saveQuietly();
    }

    public function openedLabel(LoanApplication $application): ?string
    {
        $raw = data_get($application->screening_payload, 'guided.post_approval_opened_at');
        if (! filled($raw)) {
            return null;
        }
        try {
            return Carbon::parse($raw)->diffForHumans();
        } catch (\Throwable) {
            return null;
        }
    }
}
