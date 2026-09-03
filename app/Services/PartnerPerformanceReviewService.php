<?php

namespace App\Services;

use App\Models\RecoveryAssignment;
use App\Models\Partner;
use App\Support\PartnerPerformanceStatus;
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
     * @return array{reviewed: int, nudged: int, suspended: int, recovered: int, skipped: int}
     */
    public function reviewAll(bool $applyActions = true): array
    {
        $counts = ['reviewed' => 0, 'nudged' => 0, 'suspended' => 0, 'recovered' => 0, 'skipped' => 0];

        $partners = Partner::query()
            ->whereIn('category', $this->policy->governanceCategories())
            ->where(function ($query): void {
                $query->where('status', 'active')
                    ->orWhere(function ($inner): void {
                        $inner->where('status', 'suspended')
                            ->where('suspend_kind', 'performance');
                    });
            })
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
        if (! $this->policy->isGoverned($partner)) {
            return 'skipped';
        }

        if (($partner->status ?? '') === 'suspended' && ($partner->suspend_kind ?? '') !== 'performance') {
            return 'skipped';
        }

        $row = $this->efficiency->forPartner($partner);
        if ($row === null) {
            return 'skipped';
        }

        $meta = is_array($partner->metadata) ? $partner->metadata : [];
        $snapshot = is_array($meta['efficiency'] ?? null) ? $meta['efficiency'] : [];
        $consecutive = (int) ($snapshot['consecutive_at_risk'] ?? 0);
        $action = 'skipped';
        $locale = app(PartnerTermsService::class)->partnerLocale($partner);

        if (($partner->suspend_kind ?? '') === 'performance' && ($partner->status ?? '') === 'suspended') {
            if ($applyActions && $this->policy->autoRecover() && $this->meetsRecoveryCondition($partner)) {
                $this->deletion->restoreAfterPerformance($partner);
                $partner->refresh();
                $row = $this->efficiency->forPartner($partner) ?? $row;
                $this->notifyRecovered($partner, $row, $locale);
                $snapshot['last_action'] = 'recovered';
                $snapshot['recovered_at'] = now()->toIso8601String();
                $snapshot['consecutive_at_risk'] = 0;
                $action = 'recovered';
            } else {
                $action = 'skipped';
            }

            $snapshot = array_merge($snapshot, [
                'score' => $row['score'],
                'band' => $row['band'],
                'status' => $row['status'],
                'reviewed_at' => now()->toIso8601String(),
            ]);
            $meta['efficiency'] = $snapshot;
            $partner->update([
                'metadata' => $meta,
                'performance_status' => $action === 'recovered' ? $row['status'] : PartnerPerformanceStatus::SUSPENDED,
            ]);

            return $action;
        }

        $partner->update(['performance_status' => $row['status']]);

        if ($row['band'] === PartnerEfficiencyPolicy::BAND_AT_RISK) {
            $consecutive++;
            $action = 'reviewed';

            if ($applyActions && $this->policy->autoNudge() && $this->shouldNudge($snapshot)) {
                $this->nudge($partner, $row, $consecutive, $locale);
                $snapshot['last_nudge_at'] = now()->toIso8601String();
                $action = 'nudged';
            }

            if ($applyActions && $this->policy->autoSuspend() && $consecutive >= $this->policy->warningsBeforeSuspend()) {
                $this->releaseOpenRecovery($partner);
                $this->deletion->deactivate($partner, null, 'performance');
                $snapshot['last_action'] = 'suspended';
                $snapshot['suspended_at'] = now()->toIso8601String();
                $this->notifySuspended($partner, $row, $consecutive, $locale);
                $action = 'suspended';
            }
        } else {
            $consecutive = 0;
            $snapshot['last_action'] = $row['status'];
        }

        $snapshot = array_merge($snapshot, [
            'score' => $row['score'],
            'band' => $row['band'],
            'status' => $row['status'],
            'consecutive_at_risk' => $consecutive,
            'reviewed_at' => now()->toIso8601String(),
        ]);
        $meta['efficiency'] = $snapshot;
        $partner->update([
            'metadata' => $meta,
            'performance_status' => $action === 'suspended'
                ? PartnerPerformanceStatus::SUSPENDED
                : $row['status'],
        ]);

        return $action;
    }

    public function meetsRecoveryCondition(Partner $partner): bool
    {
        $window = $this->efficiency->forPartnerInLookback($partner, $this->policy->recoverLookbackDays());

        if ($window['band'] === PartnerEfficiencyPolicy::BAND_AT_RISK) {
            return false;
        }

        if ($window['closed'] < $this->policy->minJobsForScore()) {
            return true;
        }

        if ($window['score'] !== null && $window['score'] < $this->policy->recoverMinScore()) {
            return false;
        }

        return $window['on_time_rate'] >= $this->policy->targetOnTimePercent()
            && $window['completion_rate'] >= $this->policy->targetCompletionPercent();
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
    private function nudge(Partner $partner, array $row, int $consecutive, string $locale): void
    {
        $left = max(0, $this->policy->warningsBeforeSuspend() - $consecutive);
        $this->notifications->notifyPartner($partner, 'partner_efficiency_warning', [
            'partner' => $partner->name,
            'score' => (string) ($row['score'] ?? '—'),
            'band' => $row['status_label'] ?? PartnerPerformanceStatus::label(PartnerPerformanceStatus::AT_RISK, $locale),
            'remaining' => (string) $left,
            '_fallback_subject' => trans('partner_governance.nudge_subject', [], $locale),
            '_fallback_body' => trans('partner_governance.nudge_body', [
                'name' => $partner->name,
                'score' => $row['score'] ?? '—',
                'status' => $row['status_label'] ?? '',
                'remaining' => $left,
            ], $locale),
        ]);
    }

    /** @param  array<string, mixed>  $row */
    private function notifySuspended(Partner $partner, array $row, int $consecutive, string $locale): void
    {
        $this->notifications->notifyPartner($partner, 'partner_efficiency_suspended', [
            'partner' => $partner->name,
            'score' => (string) ($row['score'] ?? '—'),
            '_fallback_subject' => trans('partner_governance.suspended_subject', [], $locale),
            '_fallback_body' => trans('partner_governance.suspended_body', [
                'name' => $partner->name,
                'reviews' => $consecutive,
            ], $locale),
        ]);
    }

    /** @param  array<string, mixed>  $row */
    private function notifyRecovered(Partner $partner, array $row, string $locale): void
    {
        $this->notifications->notifyPartner($partner, 'partner_efficiency_recovered', [
            'partner' => $partner->name,
            'score' => (string) ($row['score'] ?? '—'),
            '_fallback_subject' => trans('partner_governance.recovered_subject', [], $locale),
            '_fallback_body' => trans('partner_governance.recovered_body', [
                'name' => $partner->name,
            ], $locale),
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
