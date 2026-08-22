<?php

namespace App\Services;

use App\Models\PartnerTask;
use App\Models\RecoveryAssignment;
use App\Models\Partner;
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
                $query->whereIn('category', $this->policy->fieldCategories())
                    ->orWhereHas('tasks')
                    ->orWhereHas('recoveryAssignments');
            })
            ->orderBy('name')
            ->get();

        $taskGroups = PartnerTask::query()
            ->whereIn('partner_id', $partners->pluck('id'))
            ->get(['id', 'partner_id', 'status', 'due_at', 'completed_at', 'created_at'])
            ->groupBy('partner_id');

        $recoveryGroups = RecoveryAssignment::query()
            ->whereIn('partner_id', $partners->pluck('id'))
            ->get(['id', 'partner_id', 'status', 'sla_due_at', 'completed_at', 'assigned_at'])
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
            ->get(['id', 'partner_id', 'status', 'due_at', 'completed_at', 'created_at']);
        $assignments = RecoveryAssignment::query()
            ->where('partner_id', $partner->id)
            ->get(['id', 'partner_id', 'status', 'sla_due_at', 'completed_at', 'assigned_at']);

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

        return [
            'partner' => $partner,
            'assigned' => $assigned,
            'open' => $open,
            'completed' => $completed,
            'failed' => $failed,
            'escalated' => $escalated,
            'closed' => $closed,
            'completion_rate' => $completionRate,
            'on_time_rate' => $onTimeRate,
            'escalation_rate' => $escalationRate,
            'fail_rate' => $failRate,
            'score' => $score,
            'band' => $band,
            'band_label' => $this->policy->bandLabel($band),
            'consecutive_at_risk' => (int) ($snapshot['consecutive_at_risk'] ?? 0),
            'last_action' => $snapshot['last_action'] ?? null,
            'last_nudge_at' => $snapshot['last_nudge_at'] ?? null,
        ];
    }
}
