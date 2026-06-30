<?php

namespace App\Services;

use App\Models\RecoveryAssignment;
use App\Models\Vendor;

class RecoveryPartnerKpiService
{
    /** @return array<string, int|float|null> */
    public function kpis(Vendor $vendor): array
    {
        $base = RecoveryAssignment::query()->where('partner_id', $vendor->id);

        $assignedCases = (int) (clone $base)
            ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
            ->count();

        $totalCases = (int) (clone $base)->count();
        $completedCases = (int) (clone $base)->where('status', RecoveryAssignment::STATUS_COMPLETED)->count();

        $recoveredOutcomes = config('recovery.recovered_outcomes', ['resolved', 'sold', 'gps_removed']);
        $recoveredCases = (int) (clone $base)
            ->where('status', RecoveryAssignment::STATUS_COMPLETED)
            ->whereIn('outcome', $recoveredOutcomes)
            ->count();

        $recoveryRate = $completedCases > 0
            ? round(($recoveredCases / $completedCases) * 100, 1)
            : 0.0;

        $commissionEarned = (float) (clone $base)->sum('commission_earned');
        $commissionPaid = (float) (clone $base)->sum('commission_paid');

        $completed = (clone $base)
            ->where('status', RecoveryAssignment::STATUS_COMPLETED)
            ->whereNotNull('assigned_at')
            ->whereNotNull('completed_at')
            ->get(['assigned_at', 'completed_at']);

        $avgResolutionDays = null;
        if ($completed->isNotEmpty()) {
            $avgResolutionDays = round(
                $completed->avg(fn (RecoveryAssignment $row) => $row->assigned_at->diffInHours($row->completed_at) / 24),
                1,
            );
        }

        $slaBreaches = (int) (clone $base)
            ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS, RecoveryAssignment::STATUS_ESCALATED])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();

        return [
            'assigned_cases'        => $assignedCases,
            'total_cases'           => $totalCases,
            'completed_cases'       => $completedCases,
            'recovered_cases'       => $recoveredCases,
            'recovery_rate'         => $recoveryRate,
            'commission_earned'     => $commissionEarned,
            'commission_paid'       => $commissionPaid,
            'avg_resolution_days'   => $avgResolutionDays,
            'sla_breaches'          => $slaBreaches,
        ];
    }
}
