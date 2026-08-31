<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Queue + resume helpers for Committee and Management.
 * Reuses existing stages, workflow, and disbursement readiness — not a second credit engine.
 */
class GuidedApprovalService
{
    public function __construct(
        private readonly ScreeningSequenceService $sequence,
        private readonly PostApprovalNextActionService $postApproval,
    ) {}

    /**
     * @param  iterable<LoanApplication>  $applications
     * @return array{do_now: list<array<string, mixed>>, waiting: list<array<string, mixed>>, completed: list<array<string, mixed>>}
     */
    public function committeeQueue(iterable $applications): array
    {
        $out = ['do_now' => [], 'waiting' => [], 'completed' => []];
        foreach ($applications as $application) {
            $row = $this->committeeNext($application);
            $out[$row['bucket']][] = ['application' => $application, 'next' => $row];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function committeeNext(LoanApplication $application): array
    {
        $stage = (string) $application->current_stage;
        $clarification = data_get($application->screening_payload, 'guided.committee_clarification');
        $waitingOnScreening = $stage === 'screening'
            && is_array($clarification)
            && empty($clarification['returned_at']);
        $started = filled(data_get($application->screening_payload, 'guided.committee_opened_at'));
        $changed = $this->whatChanged($application);

        $bucket = match (true) {
            in_array($stage, ['approval', 'awaiting_management', 'disbursement'], true)
                || $application->status === 'approved' => 'completed',
            $waitingOnScreening => 'waiting',
            $stage === 'pre_approval' => 'do_now',
            default => 'completed',
        };

        $cta = match (true) {
            $waitingOnScreening => 'Waiting for Screening clarification',
            $stage === 'pre_approval' && ! empty($clarification['returned_at']) => 'Continue Committee Review',
            $stage === 'pre_approval' && $started => 'Continue Committee Review',
            $stage === 'pre_approval' => 'Start Committee Review',
            default => 'View file',
        };

        $deskHref = route('admin.loan-applications.show', [
            'loan_application' => $application,
            'workspace' => 'overview',
        ]).'#credit-workspace';

        return [
            'bucket' => $bucket,
            'cta' => $cta,
            'href' => $deskHref,
            'desk_href' => $deskHref,
            'review_href' => $waitingOnScreening
                ? $deskHref
                : route('admin.loan-applications.guided-committee', $application),
            'file_href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
            ]),
            'what_happens_next' => $waitingOnScreening
                ? 'Screening is answering Committee’s clarification. No Committee action until it returns.'
                : ($changed['has_changes'] ?? false
                    ? 'Updated since your last review — inspect only what changed, then continue.'
                    : 'Screening already established this file. Scan exceptions, then decide.'),
            'what_changed' => $changed,
        ];
    }

    /**
     * @param  iterable<LoanApplication>  $applications
     * @return array{do_now: list<array<string, mixed>>, waiting: list<array<string, mixed>>, ready: list<array<string, mixed>>, completed: list<array<string, mixed>>}
     */
    public function managementQueue(iterable $applications): array
    {
        $out = ['do_now' => [], 'waiting' => [], 'ready' => [], 'completed' => []];
        foreach ($applications as $application) {
            $row = $this->managementNext($application);
            $out[$row['bucket']][] = ['application' => $application, 'next' => $row];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function managementNext(LoanApplication $application): array
    {
        return $this->postApproval->forApplication($application);
    }

    public function markCommitteeOpened(LoanApplication $application): void
    {
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        if (empty($guided['committee_opened_at'])) {
            $guided['committee_opened_at'] = now()->toIso8601String();
        }
        if (empty($guided['committee_snapshot'])) {
            $guided['committee_snapshot'] = $this->creditSnapshot($application);
        }
        $payload['guided'] = $guided;
        $application->forceFill(['screening_payload' => $payload])->saveQuietly();
    }

    public function markPostApprovalOpened(LoanApplication $application): void
    {
        $this->postApproval->markOpened($application);
    }

    /**
     * @return array<string, mixed>
     */
    public function whatChanged(LoanApplication $application): array
    {
        $previous = data_get($application->screening_payload, 'guided.committee_snapshot');
        $current = $this->creditSnapshot($application);
        if (! is_array($previous)) {
            return ['has_changes' => false, 'items' => [], 'current' => $current];
        }

        $items = [];
        foreach ($current as $key => $value) {
            $was = $previous[$key] ?? null;
            if ($was !== $value) {
                $items[] = [
                    'key' => $key,
                    'previous' => $was,
                    'current' => $value,
                ];
            }
        }

        $clarification = data_get($application->screening_payload, 'guided.committee_clarification.response');
        if (filled($clarification)) {
            $items[] = [
                'key' => 'screening_response',
                'previous' => null,
                'current' => $clarification,
            ];
        }

        return ['has_changes' => $items !== [], 'items' => $items, 'current' => $current];
    }

    /**
     * @return array<string, mixed>
     */
    public function executiveScan(LoanApplication $application): array
    {
        $snap = $this->sequence->snapshot($application);
        $changed = $this->whatChanged($application);

        return [
            'sequence' => $snap,
            'what_changed' => $changed,
            'exceptions' => app(ScreeningExceptionService::class)->summary($application),
            'gates' => collect($snap['sequence'] ?? [])->map(fn ($row) => [
                'label' => $row['label'] ?? $row['key'] ?? '',
                'status' => $row['status'] ?? '',
                'chip' => $row['chip'] ?? '',
            ])->all(),
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    private function creditSnapshot(LoanApplication $application): array
    {
        $payload = $application->screening_payload ?? [];

        return [
            'amount' => (string) ($application->offered_amount ?: $application->requested_amount),
            'tenure' => (string) ($application->offered_tenure_months ?: $application->requested_tenure_months),
            'recommendation' => (string) ($application->recommendation_type ?? ''),
            'checklist_updated' => (string) data_get($payload, 'screening_checklist.updated_at', ''),
            'docs' => (int) $application->documentRequests()->count(),
            'exceptions_signature' => collect(data_get($payload, 'screening_exceptions', []))
                ->map(fn ($row) => implode(':', [
                    $row['id'] ?? '',
                    $row['reason'] ?? '',
                    $row['committee']['status'] ?? '',
                    $row['committee']['note'] ?? '',
                ]))
                ->implode('|'),
        ];
    }

    /**
     * One Committee scan screen at a time. Reads existing Screening results only.
     *
     * @return array<string, mixed>
     */
    public function committeeWalk(LoanApplication $application, ?int $requestedStep = null): array
    {
        $this->markCommitteeOpened($application);
        $scan = $this->executiveScan($application);
        $exceptions = $scan['exceptions'] ?? ['total' => 0, 'items' => [], 'unacknowledged_material' => 0];
        $application->loadMissing(['customer', 'product', 'collateralAssets.customerAsset']);
        $steps = [
            ['key' => 'facility', 'title' => 'Facility', 'prompt' => 'Confirm the requested facility before scanning credit evidence.'],
            ['key' => 'capacity', 'title' => 'Repayment capacity', 'prompt' => 'Screening already calculated capacity. Committee does not re-run affordability.'],
            ['key' => 'crb', 'title' => 'CRB', 'prompt' => 'Review the compact bureau result. Only material issues need attention.'],
            ['key' => 'people', 'title' => 'People', 'prompt' => 'Identity, NOK and LGO were completed in Screening. Inspect a participant only if needed.'],
            ['key' => 'security', 'title' => 'Security', 'prompt' => 'Collateral values come from the same record Screening and Valuation used.'],
            ['key' => 'recommendation', 'title' => 'Recommendation', 'prompt' => 'This is the only place to challenge Screening. Then record the Committee decision.'],
        ];
        if ((int) ($exceptions['total'] ?? 0) > 0) {
            array_unshift($steps, [
                'key' => 'exceptions',
                'title' => 'Exceptions from Screening',
                'prompt' => 'The system raised these findings and Screening disagreed. Review the original recommendation, the analyst decision, and the rationale. Do not re-do Screening.',
            ]);
        }
        $saved = (int) data_get($application->screening_payload, 'guided.committee_step', 1);
        $index = $requestedStep ?: $saved;
        $index = max(1, min(count($steps), $index));
        if ((int) ($exceptions['unacknowledged_material'] ?? 0) > 0
            && ($steps[$index - 1]['key'] ?? '') === 'recommendation') {
            $index = 1;
        }
        $this->persistCommitteeStep($application, $index);
        $step = $steps[$index - 1];
        $changed = $scan['what_changed'] ?? ['has_changes' => false, 'items' => []];

        return [
            'index' => $index,
            'total' => count($steps),
            'percent' => (int) round(($index / max(1, count($steps))) * 100),
            'step' => $step,
            'scan' => $scan,
            'exceptions' => $exceptions,
            'block_decision' => (int) ($exceptions['unacknowledged_material'] ?? 0) > 0,
            'changed' => $changed,
            'next_index' => $index < count($steps) ? $index + 1 : null,
            'prev_index' => $index > 1 ? $index - 1 : null,
            'decision_href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'decision',
            ]).'#review-action-zone',
            'file_href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
            ]),
            'crb_href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'desk_phase' => 'capacity',
                'capacity_tab' => 'crb',
                'from' => 'committee',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function postApprovalWalk(LoanApplication $application): array
    {
        $this->markPostApprovalOpened($application);
        $next = $this->postApproval->forApplication($application);
        $condition = $next['condition'] ?? null;
        $waiting = ($next['bucket'] ?? '') === PostApprovalNextActionService::BUCKET_WAITING;
        $ready = ($next['bucket'] ?? '') === PostApprovalNextActionService::BUCKET_READY;

        return [
            'next' => $next,
            'checklist' => $next['checklist'] ?? [],
            'conditions' => $next['conditions'] ?? [],
            'condition' => $condition,
            'waiting' => $waiting,
            'ready' => $ready,
            'contract_ready' => (bool) ($next['contract_ready'] ?? false),
            'contract_readiness' => $next['contract_readiness'] ?? [],
            'disbursement_ready' => (bool) ($next['disbursement_ready'] ?? false),
            'desk_href' => $next['desk_href'] ?? route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ]).'#credit-management-desk',
            'file_href' => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
            ]),
        ];
    }

    private function persistCommitteeStep(LoanApplication $application, int $index): void
    {
        $payload = $application->screening_payload ?? [];
        $guided = (array) ($payload['guided'] ?? []);
        if ((int) ($guided['committee_step'] ?? 0) === $index) {
            return;
        }
        $guided['committee_step'] = $index;
        $payload['guided'] = $guided;
        $application->forceFill(['screening_payload' => $payload])->saveQuietly();
    }

    public function openedLabel(LoanApplication $application, string $key): ?string
    {
        $raw = data_get($application->screening_payload, 'guided.'.$key);
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
