<?php

namespace App\Services;

use App\Models\PartnerTask;
use App\Models\RecoveryAssignment;
use App\Models\Partner;
use App\Support\PartnerPerformanceStatus;
use Illuminate\Support\Collection;

class PartnerEfficiencyService
{
    public const BAND_STRONG = PartnerEfficiencyPolicy::BAND_STRONG;

    public const BAND_WATCH = PartnerEfficiencyPolicy::BAND_WATCH;

    public const BAND_AT_RISK = PartnerEfficiencyPolicy::BAND_AT_RISK;

    public const BAND_NEW = PartnerEfficiencyPolicy::BAND_NEW;

    public function __construct(private readonly PartnerEfficiencyPolicy $policy) {}

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, coaching: Collection<int, array<string, mixed>>, leaders: Collection<int, array<string, mixed>>, policy: PartnerEfficiencyPolicy}
     */
    public function board(): array
    {
        $partners = Partner::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereIn('category', $this->policy->governanceCategories())
                    ->orWhereHas('tasks')
                    ->orWhereHas('recoveryAssignments');
            })
            ->orderBy('name')
            ->get();

        $taskGroups = PartnerTask::query()
            ->whereIn('partner_id', $partners->pluck('id'))
            ->get(['id', 'partner_id', 'status', 'due_at', 'completed_at', 'created_at', 'accepted_at', 'notes'])
            ->groupBy('partner_id');

        $recoveryGroups = RecoveryAssignment::query()
            ->whereIn('partner_id', $partners->pluck('id'))
            ->get(['id', 'partner_id', 'status', 'sla_due_at', 'completed_at', 'assigned_at', 'outcome', 'notes'])
            ->groupBy('partner_id');

        $rows = $partners->map(function (Partner $partner) use ($taskGroups, $recoveryGroups) {
            return $this->scorePartner(
                $partner,
                $taskGroups->get($partner->id, collect()),
                $recoveryGroups->get($partner->id, collect()),
            );
        })->sortBy([
            fn (array $row) => $row['band'] === self::BAND_NEW ? 1 : 0,
            ['score', 'desc'],
            fn (array $row) => strtolower((string) $row['partner']->name),
        ])->values();

        $coaching = $rows
            ->filter(fn (array $row) => $row['band'] === self::BAND_AT_RISK)
            ->values();

        $leaders = $rows
            ->filter(fn (array $row) => $row['band'] === self::BAND_STRONG)
            ->take(8)
            ->values();

        return [
            'rows' => $rows,
            'coaching' => $coaching,
            'leaders' => $leaders,
            'policy' => $this->policy,
        ];
    }

    /** @return array<string, mixed>|null */
    public function forPartner(Partner $partner): ?array
    {
        if (! $this->policy->appliesTo($partner)) {
            return null;
        }

        $tasks = PartnerTask::query()
            ->where('partner_id', $partner->id)
            ->get(['id', 'partner_id', 'status', 'due_at', 'completed_at', 'created_at', 'accepted_at', 'notes']);
        $assignments = RecoveryAssignment::query()
            ->where('partner_id', $partner->id)
            ->get(['id', 'partner_id', 'status', 'sla_due_at', 'completed_at', 'assigned_at', 'outcome', 'notes']);

        return $this->scorePartner($partner, $tasks, $assignments);
    }

    /**
     * @param  Collection<int, PartnerTask>  $tasks
     * @param  Collection<int, RecoveryAssignment>  $assignments
     * @return array<string, mixed>
     */
    public function scorePartner(Partner $partner, Collection $tasks, Collection $assignments): array
    {
        $assigned = $tasks->count() + $assignments->count();
        $open = $tasks->whereIn('status', ['assigned', 'in_progress'])->count()
            + $assignments->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])->count();
        $completed = $tasks->where('status', 'completed')->count()
            + $assignments->where('status', RecoveryAssignment::STATUS_COMPLETED)->count();
        $failed = $tasks->whereIn('status', ['failed', 'cancelled'])->count()
            + $assignments->whereIn('status', [RecoveryAssignment::STATUS_FAILED, RecoveryAssignment::STATUS_CANCELLED])->count();
        $escalated = $assignments->where('status', RecoveryAssignment::STATUS_ESCALATED)->count();
        $closed = $completed + $failed + $escalated;

        $accepted = $tasks->filter(fn (PartnerTask $task) => $task->accepted_at !== null)->count()
            + $assignments->whereIn('status', [
                RecoveryAssignment::STATUS_IN_PROGRESS,
                RecoveryAssignment::STATUS_COMPLETED,
                RecoveryAssignment::STATUS_ESCALATED,
            ])->count();

        $slaBreaches = 0;
        $reassignments = 0;
        $turnaroundHours = [];

        foreach ($tasks as $task) {
            $meta = $task->notesMeta();
            if (! empty($meta['sla_breached_at'])) {
                $slaBreaches++;
            } elseif ($task->due_at && $task->status === 'completed' && $task->completed_at && $task->completed_at->gt($task->due_at)) {
                $slaBreaches++;
            }
            $reassignments += (int) ($meta['reassignment_count'] ?? 0);
            if ($task->status === 'completed' && $task->completed_at) {
                $start = $task->created_at;
                if ($start) {
                    $turnaroundHours[] = max(0, $start->diffInHours($task->completed_at));
                }
            }
        }
        foreach ($assignments as $assignment) {
            if ($assignment->sla_due_at && (
                $assignment->status === RecoveryAssignment::STATUS_ESCALATED
                || ($assignment->status === RecoveryAssignment::STATUS_COMPLETED && $assignment->completed_at && $assignment->completed_at->gt($assignment->sla_due_at))
                || ($assignment->isOpen() && $assignment->sla_due_at->isPast())
            )) {
                $slaBreaches++;
            }
            if (($assignment->outcome ?? '') === 'reassigned') {
                $reassignments++;
            }
            if ($assignment->status === RecoveryAssignment::STATUS_COMPLETED && $assignment->assigned_at && $assignment->completed_at) {
                $turnaroundHours[] = max(0, $assignment->assigned_at->diffInHours($assignment->completed_at));
            }
        }

        $onTime = 0;
        foreach ($tasks->where('status', 'completed') as $task) {
            if (! $task->due_at || ! $task->completed_at || $task->completed_at->lte($task->due_at)) {
                $onTime++;
            }
        }
        foreach ($assignments->where('status', RecoveryAssignment::STATUS_COMPLETED) as $assignment) {
            if (! $assignment->sla_due_at || ! $assignment->completed_at || $assignment->completed_at->lte($assignment->sla_due_at)) {
                $onTime++;
            }
        }

        $completionRate = $closed > 0 ? round(($completed / $closed) * 100, 1) : 0.0;
        $onTimeRate = $completed > 0 ? round(($onTime / $completed) * 100, 1) : 0.0;
        $escalationRate = $assigned > 0 ? round(($escalated / $assigned) * 100, 1) : 0.0;
        $failRate = $closed > 0 ? round(($failed / $closed) * 100, 1) : 0.0;

        $wCompletion = $this->policy->weightCompletion() / 100;
        $wOnTime = $this->policy->weightOnTime() / 100;
        $wEscalated = $this->policy->weightNotEscalated() / 100;
        $wFailed = $this->policy->weightNotFailed() / 100;

        $hasScore = $closed >= $this->policy->minJobsForScore();
        $score = $hasScore
            ? (int) round(
                ($wCompletion * $completionRate)
                + ($wOnTime * $onTimeRate)
                + ($wEscalated * (100 - $escalationRate))
                + ($wFailed * (100 - $failRate))
            )
            : null;

        $band = self::BAND_NEW;
        if ($hasScore && $score !== null) {
            $band = $score >= $this->policy->strongScore()
                ? self::BAND_STRONG
                : ($score >= $this->policy->watchScore() ? self::BAND_WATCH : self::BAND_AT_RISK);
        }

        if ($hasScore && (
            $escalationRate >= $this->policy->forceAtRiskEscalationPercent()
            || $failRate >= $this->policy->forceAtRiskFailPercent()
        )) {
            $band = self::BAND_AT_RISK;
        }

        $snapshot = is_array($partner->metadata['efficiency'] ?? null) ? $partner->metadata['efficiency'] : [];
        $presentation = $this->presentationStatus($partner, $band, $score);
        $why = $this->whyStatus($partner, $presentation, $onTimeRate, $completionRate, $closed, $band);
        $nextReview = $this->policy->nextReviewAt();

        return [
            'partner' => $partner,
            'assigned' => $assigned,
            'accepted' => $accepted,
            'open' => $open,
            'completed' => $completed,
            'failed' => $failed,
            'escalated' => $escalated,
            'closed' => $closed,
            'sla_breaches' => $slaBreaches,
            'reassignments' => $reassignments,
            'avg_turnaround_hours' => $turnaroundHours === [] ? null : (int) round(array_sum($turnaroundHours) / count($turnaroundHours)),
            'completion_rate' => $completionRate,
            'on_time_rate' => $onTimeRate,
            'escalation_rate' => $escalationRate,
            'fail_rate' => $failRate,
            'score' => $score,
            'band' => $band,
            'band_label' => $this->policy->bandLabel($band, null, $score),
            'status' => $presentation,
            'status_label' => PartnerPerformanceStatus::label($presentation),
            'target_on_time_percent' => $this->policy->targetOnTimePercent(),
            'target_completion_percent' => $this->policy->targetCompletionPercent(),
            'why' => $why,
            'next_action' => $this->nextAction($partner, $presentation, $nextReview),
            'next_review_at' => $nextReview,
            'kpi_rows' => $this->kpiRows($onTimeRate, $completionRate, $slaBreaches, $reassignments, $completed, $turnaroundHours === [] ? null : (int) round(array_sum($turnaroundHours) / count($turnaroundHours))),
            'consecutive_at_risk' => (int) ($snapshot['consecutive_at_risk'] ?? 0),
            'last_action' => $snapshot['last_action'] ?? null,
            'last_nudge_at' => $snapshot['last_nudge_at'] ?? null,
        ];
    }

    /**
     * Score using jobs/cases whose activity falls inside the lookback window (auto-recovery).
     *
     * @return array<string, mixed>
     */
    public function forPartnerInLookback(Partner $partner, int $days): array
    {
        $since = now()->subDays(max(1, $days));
        $tasks = PartnerTask::query()
            ->where('partner_id', $partner->id)
            ->where(function ($query) use ($since): void {
                $query->where('created_at', '>=', $since)
                    ->orWhere('completed_at', '>=', $since);
            })
            ->get(['id', 'partner_id', 'status', 'due_at', 'completed_at', 'created_at', 'accepted_at', 'notes']);
        $assignments = RecoveryAssignment::query()
            ->where('partner_id', $partner->id)
            ->where(function ($query) use ($since): void {
                $query->where('assigned_at', '>=', $since)
                    ->orWhere('completed_at', '>=', $since);
            })
            ->get(['id', 'partner_id', 'status', 'sla_due_at', 'completed_at', 'assigned_at', 'outcome', 'notes']);

        return $this->scorePartner($partner, $tasks, $assignments);
    }

    /**
     * Compact performance cells for a partner list page (avoids N+1).
     *
     * @param  Collection<int, Partner>  $partners
     * @return array<int, array{label: string, band: string, score: int|null}|null>
     */
    public function summariesFor(Collection $partners): array
    {
        $ids = $partners->pluck('id')->filter()->values();
        $taskGroups = PartnerTask::query()
            ->whereIn('partner_id', $ids)
            ->get(['id', 'partner_id', 'status', 'due_at', 'completed_at', 'created_at', 'accepted_at', 'notes'])
            ->groupBy('partner_id');
        $recoveryGroups = RecoveryAssignment::query()
            ->whereIn('partner_id', $ids)
            ->get(['id', 'partner_id', 'status', 'sla_due_at', 'completed_at', 'assigned_at', 'outcome', 'notes'])
            ->groupBy('partner_id');

        $lifecycle = app(AffiliateLifecycleService::class);
        $governance = $this->policy->governanceCategories();
        $out = [];

        foreach ($partners as $partner) {
            if ($partner->isAffiliate() || $partner->hasPartnerRole('affiliate')) {
                $status = $lifecycle->statusFor($partner);
                $out[$partner->id] = [
                    'label' => $lifecycle->label($status),
                    'band' => $status,
                    'score' => null,
                ];

                continue;
            }

            $hasWork = $taskGroups->has($partner->id) || $recoveryGroups->has($partner->id);
            $gauged = in_array((string) $partner->category, $governance, true) || $hasWork;

            if (! $gauged) {
                $out[$partner->id] = null;

                continue;
            }

            $row = $this->scorePartner(
                $partner,
                $taskGroups->get($partner->id, collect()),
                $recoveryGroups->get($partner->id, collect()),
            );
            $out[$partner->id] = [
                'label' => $row['status_label'],
                'band' => $row['status'],
                'score' => $row['score'],
            ];
        }

        return $out;
    }

    public function presentationStatus(Partner $partner, string $band, ?int $score): string
    {
        if (($partner->status ?? '') === 'suspended' && ($partner->suspend_kind ?? '') === 'performance') {
            return PartnerPerformanceStatus::SUSPENDED;
        }

        return $this->policy->presentationStatusForBand($band, $score);
    }

    /**
     * @return list<array{key: string, label: string, actual: string, target: string, met: bool|null}>
     */
    private function kpiRows(
        float $onTimeRate,
        float $completionRate,
        int $slaBreaches,
        int $reassignments,
        int $completed,
        ?int $avgTurnaroundHours,
    ): array {
        $onTimeTarget = $this->policy->targetOnTimePercent();
        $completionTarget = $this->policy->targetCompletionPercent();

        $rows = [
            [
                'key' => 'on_time',
                'label' => __('partner_governance.kpi_on_time'),
                'actual' => $onTimeRate.'%',
                'target' => $onTimeTarget.'%',
                'met' => $onTimeRate >= $onTimeTarget,
            ],
            [
                'key' => 'completion',
                'label' => __('partner_governance.kpi_completion'),
                'actual' => $completionRate.'%',
                'target' => $completionTarget.'%',
                'met' => $completionRate >= $completionTarget,
            ],
            [
                'key' => 'sla_breaches',
                'label' => __('partner_governance.kpi_sla_breaches'),
                'actual' => (string) $slaBreaches,
                'target' => '—',
                'met' => null,
            ],
            [
                'key' => 'reassignments',
                'label' => __('partner_governance.kpi_reassignments'),
                'actual' => (string) $reassignments,
                'target' => '—',
                'met' => null,
            ],
            [
                'key' => 'completed',
                'label' => __('partner_governance.kpi_completed'),
                'actual' => (string) $completed,
                'target' => '—',
                'met' => null,
            ],
        ];

        if ($avgTurnaroundHours !== null) {
            $rows[] = [
                'key' => 'turnaround',
                'label' => __('partner_governance.kpi_turnaround'),
                'actual' => $avgTurnaroundHours.'h',
                'target' => '—',
                'met' => null,
            ];
        }

        return $rows;
    }

    private function whyStatus(
        Partner $partner,
        string $status,
        float $onTimeRate,
        float $completionRate,
        int $closed,
        string $band,
    ): string {
        $onTimeTarget = $this->policy->targetOnTimePercent();
        $completionTarget = $this->policy->targetCompletionPercent();

        if ($status === PartnerPerformanceStatus::SUSPENDED) {
            $kind = (string) ($partner->suspend_kind ?: 'performance');

            return $kind === 'performance'
                ? __('partner_governance.why_suspended_performance')
                : __('partner_governance.why_suspended_other', ['kind' => $kind]);
        }
        if ($status === PartnerPerformanceStatus::RAMP_UP) {
            return __('partner_governance.why_ramp_up', ['min' => $this->policy->minJobsForScore()]);
        }
        if ($onTimeRate < $onTimeTarget) {
            return __('partner_governance.why_on_time', ['actual' => $onTimeRate, 'target' => $onTimeTarget]);
        }
        if ($completionRate < $completionTarget) {
            return __('partner_governance.why_completion', ['actual' => $completionRate, 'target' => $completionTarget]);
        }
        if ($band === PartnerEfficiencyPolicy::BAND_AT_RISK) {
            return __('partner_governance.why_at_risk');
        }
        if ($status === PartnerPerformanceStatus::EXCELLENT) {
            return __('partner_governance.why_excellent');
        }

        return __('partner_governance.why_good');
    }

    private function nextAction(Partner $partner, string $status, $nextReview): string
    {
        $date = $nextReview->format('d M Y');

        if ($status === PartnerPerformanceStatus::SUSPENDED) {
            if (($partner->suspend_kind ?? '') === 'performance' && $this->policy->autoRecover()) {
                return __('partner_governance.next_recover', ['date' => $date]);
            }

            return __('partner_governance.next_suspended_manual');
        }

        return __('partner_governance.next_reassess', ['date' => $date]);
    }
}
