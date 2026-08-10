<?php

namespace App\Services;

use App\Models\PartnerTask;
use App\Models\RecoveryAssignment;
use App\Models\Vendor;
use Illuminate\Support\Collection;

class PartnerAutoAssignSelector
{
    public function __construct(
        private readonly PartnerAutoAssignPolicy $policy,
        private readonly RecoveryPartnerKpiService $kpis,
    ) {}

    /**
     * @param  Collection<int, Vendor>  $candidates
     * @param  list<int>  $excludeIds
     */
    public function pickRecovery(string $type, Collection $candidates, array $excludeIds = []): ?Vendor
    {
        $settings = $this->policy->forRecoveryType($type);
        if (! $this->policy->enabledForRecovery($type)) {
            return null;
        }

        return $this->pick($candidates, $settings, 'recovery', $excludeIds);
    }

    /**
     * @param  Collection<int, Vendor>  $candidates
     * @param  list<int>  $excludeIds
     */
    public function pickService(string $category, Collection $candidates, array $excludeIds = []): ?Vendor
    {
        $settings = $this->policy->forServiceCategory($category);
        if (! $this->policy->enabledForService($category)) {
            return null;
        }

        return $this->pick($candidates, $settings, 'service', $excludeIds);
    }

    /**
     * @param  Collection<int, Vendor>  $candidates
     * @param  array<string, mixed>  $settings
     * @param  list<int>  $excludeIds
     */
    private function pick(Collection $candidates, array $settings, string $domain, array $excludeIds = []): ?Vendor
    {
        $eligible = $candidates
            ->filter(fn (Vendor $vendor) => $vendor->status === 'active')
            ->reject(fn (Vendor $vendor) => in_array((int) $vendor->id, array_map('intval', $excludeIds), true))
            ->values();

        if ($eligible->isEmpty()) {
            return null;
        }

        $maxOpen = $settings['max_open'] ?? null;
        $scored = $eligible->map(function (Vendor $vendor) use ($domain, $settings, $maxOpen) {
            $open = $this->openCount($vendor, $domain);
            if ($maxOpen !== null && $open >= (int) $maxOpen) {
                return null;
            }

            $metrics = $this->metrics($vendor, $domain, $settings);

            return [
                'vendor' => $vendor,
                'open' => $open,
                'efficiency' => $metrics['efficiency'],
                'fairness_hours' => $metrics['fairness_hours'],
                'last_assigned_at' => $metrics['last_assigned_at'],
            ];
        })->filter()->values();

        if ($scored->isEmpty()) {
            return null;
        }

        $strategy = (string) ($settings['strategy'] ?? 'least_load');

        return match ($strategy) {
            'round_robin' => $this->byRoundRobin($scored),
            'efficiency_balanced' => $this->byEfficiencyBalanced($scored, $settings),
            default => $this->byLeastLoad($scored),
        };
    }

    /** @param  Collection<int, array<string, mixed>>  $scored */
    private function byLeastLoad(Collection $scored): Vendor
    {
        return $scored
            ->sortBy([
                ['open', 'asc'],
                ['fairness_hours', 'desc'],
                fn ($row) => strtolower((string) $row['vendor']->name),
            ])
            ->first()['vendor'];
    }

    /** @param  Collection<int, array<string, mixed>>  $scored */
    private function byRoundRobin(Collection $scored): Vendor
    {
        return $scored
            ->sortBy([
                ['last_assigned_at', 'asc'],
                ['open', 'asc'],
                fn ($row) => strtolower((string) $row['vendor']->name),
            ])
            ->first()['vendor'];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scored
     * @param  array<string, mixed>  $settings
     */
    private function byEfficiencyBalanced(Collection $scored, array $settings): Vendor
    {
        $maxOpen = max(1, (int) $scored->max('open'));
        $maxFair = max(1.0, (float) $scored->max('fairness_hours'));

        $wLoad = ((int) ($settings['weight_load'] ?? 40)) / 100;
        $wEff = ((int) ($settings['weight_efficiency'] ?? 50)) / 100;
        $wFair = ((int) ($settings['weight_fairness'] ?? 10)) / 100;

        return $scored
            ->map(function (array $row) use ($maxOpen, $maxFair, $wLoad, $wEff, $wFair) {
                $loadScore = 1 - (((int) $row['open']) / $maxOpen);
                $effScore = ((float) $row['efficiency']) / 100;
                $fairScore = ((float) $row['fairness_hours']) / $maxFair;
                $row['score'] = ($wLoad * $loadScore) + ($wEff * $effScore) + ($wFair * $fairScore);

                return $row;
            })
            ->sortByDesc('score')
            ->first()['vendor'];
    }

    private function openCount(Vendor $vendor, string $domain): int
    {
        if ($domain === 'recovery') {
            return (int) RecoveryAssignment::query()
                ->where('partner_id', $vendor->id)
                ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
                ->count();
        }

        return (int) PartnerTask::query()
            ->where('partner_id', $vendor->id)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->count();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{efficiency: float, fairness_hours: float, last_assigned_at: \Carbon\Carbon|null}
     */
    private function metrics(Vendor $vendor, string $domain, array $settings): array
    {
        $coldStart = (float) ($settings['cold_start_rate'] ?? 50);

        if ($domain === 'recovery') {
            $kpi = $this->kpis->kpis($vendor);
            $efficiency = ((int) ($kpi['completed_cases'] ?? 0)) > 0
                ? (float) ($kpi['recovery_rate'] ?? 0)
                : $coldStart;

            $last = RecoveryAssignment::query()
                ->where('partner_id', $vendor->id)
                ->whereNotNull('assigned_at')
                ->orderByDesc('assigned_at')
                ->value('assigned_at');

            $lastAt = $last ? \Carbon\Carbon::parse($last) : null;

            return [
                'efficiency' => $efficiency,
                'fairness_hours' => $lastAt ? $lastAt->diffInHours(now()) : 10_000.0,
                'last_assigned_at' => $lastAt ?? now()->subYears(10),
            ];
        }

        $base = PartnerTask::query()->where('partner_id', $vendor->id);
        $completed = (int) (clone $base)->where('status', 'completed')->count();
        $totalClosed = (int) (clone $base)->whereIn('status', ['completed', 'cancelled', 'failed'])->count();
        $efficiency = $totalClosed > 0
            ? round(($completed / max(1, $totalClosed)) * 100, 1)
            : $coldStart;

        $last = PartnerTask::query()
            ->where('partner_id', $vendor->id)
            ->orderByDesc('created_at')
            ->value('created_at');
        $lastAt = $last ? \Carbon\Carbon::parse($last) : null;

        return [
            'efficiency' => $efficiency,
            'fairness_hours' => $lastAt ? $lastAt->diffInHours(now()) : 10_000.0,
            'last_assigned_at' => $lastAt ?? now()->subYears(10),
        ];
    }
}
