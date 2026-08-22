<?php

namespace App\Services;

use App\Models\RecoveryAssignment;
use App\Models\Partner;
use Illuminate\Support\Carbon;

class PartnerPerformanceReviewService
{
    public function __construct(
        private readonly PartnerEfficiencyService $efficiency,
        private readonly PartnerEfficiencyPolicy $policy,
        private readonly PartnerDeletionService $deletion,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @return array{reviewed: int, nudged: int, suspended: int, skipped: int}
     */
    public function reviewAll(bool $applyActions = true): array
    {
        $counts = ['reviewed' => 0, 'nudged' => 0, 'suspended' => 0, 'skipped' => 0];

        $partners = Partner::query()
            ->where('status', 'active')
            ->whereIn('category', $this->policy->fieldCategories())
            ->get();

        foreach ($partners as $partner) {
            $result = $this->reviewPartner($partner, $applyActions);
            $counts['reviewed']++;
            if ($result !== 'reviewed') {
                $counts[$result] = ($counts[$result] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function reviewPartner(Partner $partner, bool $applyActions = true): string
    {
        $row = $this->efficiency->forPartner($partner);
        if ($row === null) {
            return 'skipped';
        }

        $meta = is_array($partner->metadata) ? $partner->metadata : [];
        $snapshot = is_array($meta['efficiency'] ?? null) ? $meta['efficiency'] : [];
        $consecutive = (int) ($snapshot['consecutive_at_risk'] ?? 0);
        $action = 'skipped';

        if ($row['band'] === PartnerEfficiencyPolicy::BAND_AT_RISK) {
            $consecutive++;
            $action = 'reviewed';

            if ($applyActions && $this->policy->autoNudge() && $this->shouldNudge($snapshot)) {
                $this->nudge($partner, $row, $consecutive);
                $snapshot['last_nudge_at'] = now()->toIso8601String();
                $action = 'nudged';
            }

            if ($applyActions && $this->policy->autoSuspend() && $consecutive >= $this->policy->warningsBeforeSuspend()) {
                $this->releaseOpenRecovery($partner);
                $this->deletion->deactivate($partner);
                $snapshot['last_action'] = 'suspended';
                $snapshot['suspended_at'] = now()->toIso8601String();
                $this->notifySuspended($partner, $row, $consecutive);
                $action = 'suspended';
            }
        } else {
            $consecutive = 0;
            $snapshot['last_action'] = $row['band'];
        }

        $snapshot = array_merge($snapshot, [
            'score' => $row['score'],
            'band' => $row['band'],
            'consecutive_at_risk' => $consecutive,
            'reviewed_at' => now()->toIso8601String(),
        ]);
        $meta['efficiency'] = $snapshot;
        $partner->update(['metadata' => $meta]);

        return $action;
    }

    /** @param  array<string, mixed>  $snapshot */
    private function shouldNudge(array $snapshot): bool
    {
        $last = $snapshot['last_nudge_at'] ?? null;
        if (! is_string($last) || $last === '') {
            return true;
        }

        return Carbon::parse($last)->lte(now()->subDays($this->policy->nudgeCooldownDays()));
    }

    /** @param  array<string, mixed>  $row */
    private function nudge(Partner $partner, array $row, int $consecutive): void
    {
        $left = max(0, $this->policy->warningsBeforeSuspend() - $consecutive);
        $this->notifications->notifyPartner($partner, 'partner_efficiency_warning', [
            'partner' => $partner->name,
            'score' => (string) ($row['score'] ?? '—'),
            'band' => $row['band_label'] ?? 'Needs coaching',
            'remaining' => (string) $left,
            '_fallback_subject' => 'Performance is below the bar',
            '_fallback_body' => 'Hi '.$partner->name.', your job score is '.($row['score'] ?? '—').' (needs coaching). Pull up — if this continues, the account will be suspended. '.$left.' warning(s) left. — '.(function_exists('brand_name') ? brand_name() : 'KopaFasta'),
        ]);
    }

    /** @param  array<string, mixed>  $row */
    private function notifySuspended(Partner $partner, array $row, int $consecutive): void
    {
        $this->notifications->notifyPartner($partner, 'partner_efficiency_suspended', [
            'partner' => $partner->name,
            'score' => (string) ($row['score'] ?? '—'),
            '_fallback_subject' => 'Account suspended for low performance',
            '_fallback_body' => 'Hi '.$partner->name.', your account was suspended after '.$consecutive.' coaching reviews. Contact Partner support to be reactivated. — '.(function_exists('brand_name') ? brand_name() : 'KopaFasta'),
        ]);
    }

    private function releaseOpenRecovery(Partner $partner): void
    {
        RecoveryAssignment::query()
            ->where('partner_id', $partner->id)
            ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
            ->update([
                'status' => RecoveryAssignment::STATUS_CANCELLED,
                'outcome' => 'partner_suspended',
                'completed_at' => now(),
                'notes' => 'Released because the partner was suspended for low performance.',
            ]);
    }
}
