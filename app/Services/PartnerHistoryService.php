<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\PartnerTask;
use App\Models\AuditLog;
use Illuminate\Support\Collection;

class PartnerHistoryService
{
    /**
     * Human-readable partner history from existing records (no second event store).
     *
     * @return list<array{at: \Illuminate\Support\Carbon|null, label: string, detail: string}>
     */
    public function for(Partner $partner): array
    {
        $events = [];

        $application = PartnerApplication::query()
            ->where('partner_id', $partner->id)
            ->latest()
            ->first();
        if ($application) {
            $events[] = [
                'at' => $application->created_at,
                'label' => __('partner_governance.history_applied'),
                'detail' => $application->categoryLabel(),
            ];
            if ($application->reviewed_at) {
                $events[] = [
                    'at' => $application->reviewed_at,
                    'label' => __('partner_governance.history_approved'),
                    'detail' => (string) ($application->status ?? ''),
                ];
            }
        }

        if ($partner->activated_at) {
            $events[] = [
                'at' => $partner->activated_at,
                'label' => __('partner_governance.history_activated'),
                'detail' => '',
            ];
        }

        foreach (app(PartnerTermsService::class)->history($partner) as $acceptance) {
            $events[] = [
                'at' => $acceptance->accepted_at,
                'label' => __('partner_governance.history_terms'),
                'detail' => 'v'.$acceptance->agreement_version.' / policy v'.$acceptance->policy_version.' · '.$acceptance->locale,
            ];
        }

        if ($partner->membership_started_at) {
            $events[] = [
                'at' => $partner->membership_started_at,
                'label' => __('partner_governance.history_membership'),
                'detail' => $partner->membership_expires_at?->format('d M Y') ?? '',
            ];
        }

        $efficiency = is_array($partner->metadata['efficiency'] ?? null) ? $partner->metadata['efficiency'] : [];
        if (! empty($efficiency['last_nudge_at'])) {
            $events[] = [
                'at' => \Illuminate\Support\Carbon::parse($efficiency['last_nudge_at']),
                'label' => __('partner_governance.history_warning'),
                'detail' => '',
            ];
        }
        if (! empty($efficiency['suspended_at'])) {
            $events[] = [
                'at' => \Illuminate\Support\Carbon::parse($efficiency['suspended_at']),
                'label' => __('partner_governance.history_suspended'),
                'detail' => (string) ($partner->suspend_kind ?: 'performance'),
            ];
        }
        if (! empty($efficiency['recovered_at'])) {
            $events[] = [
                'at' => \Illuminate\Support\Carbon::parse($efficiency['recovered_at']),
                'label' => __('partner_governance.history_recovered'),
                'detail' => '',
            ];
        }

        foreach (PartnerTask::query()->where('partner_id', $partner->id)->orderByDesc('id')->limit(8)->get() as $task) {
            $events[] = [
                'at' => $task->created_at,
                'label' => __('partner_governance.history_task_assigned'),
                'detail' => (string) $task->task_type.' #'.$task->id,
            ];
        }

        foreach (\App\Models\RecoveryAssignment::query()->where('partner_id', $partner->id)->orderByDesc('id')->limit(8)->get() as $assignment) {
            $events[] = [
                'at' => $assignment->assigned_at ?: $assignment->created_at,
                'label' => __('partner_governance.history_case_assigned'),
                'detail' => (string) $assignment->partner_type.' #'.$assignment->id,
            ];
        }

        $breached = PartnerTask::query()
            ->where('partner_id', $partner->id)
            ->whereNotNull('notes')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        foreach ($breached as $task) {
            $at = $task->notesMeta()['sla_breached_at'] ?? null;
            if (! $at) {
                continue;
            }
            $events[] = [
                'at' => \Illuminate\Support\Carbon::parse($at),
                'label' => __('partner_governance.history_sla_breach'),
                'detail' => (string) $task->task_type.' #'.$task->id,
            ];
        }

        $logs = AuditLog::query()
            ->where('auditable_type', $partner->getMorphClass())
            ->where('auditable_id', $partner->getKey())
            ->whereIn('event', [
                'partner.performance_suspended',
                'partner.performance_recovered',
                'partner.admin_suspended',
                'partner.compliance_suspended',
                'partner.fraud_suspended',
            ])
            ->latest()
            ->limit(20)
            ->get();
        foreach ($logs as $log) {
            $events[] = [
                'at' => $log->created_at,
                'label' => str_replace('_', ' ', str_replace('partner.', '', (string) $log->event)),
                'detail' => '',
            ];
        }

        return collect($events)
            ->filter(fn (array $row) => $row['at'] !== null)
            ->sortByDesc(fn (array $row) => $row['at']?->timestamp ?? 0)
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, url: ?string, source: string}>
     */
    public function documents(Partner $partner): array
    {
        $out = [];
        $application = PartnerApplication::query()
            ->with('documents')
            ->where('partner_id', $partner->id)
            ->latest()
            ->first();
        if ($application) {
            foreach ($application->documents as $doc) {
                $out[] = [
                    'label' => $doc->label(),
                    'url' => $doc->url(),
                    'source' => __('partner_governance.documents_application'),
                ];
            }
        }

        foreach ($partner->documents()->orderByDesc('id')->limit(50)->get() as $doc) {
            $out[] = [
                'label' => (string) ($doc->label ?? $doc->doc_type ?? 'Document'),
                'url' => $doc->file_path ? asset('storage/'.$doc->file_path) : null,
                'source' => __('partner_governance.documents_partner'),
            ];
        }

        $identity = is_array($partner->metadata['identity'] ?? null) ? $partner->metadata['identity'] : [];
        foreach (['national_id_front' => 'National ID (front)', 'national_id_back' => 'National ID (back)'] as $key => $label) {
            if (filled($identity[$key] ?? null)) {
                $out[] = [
                    'label' => $label,
                    'url' => asset('storage/'.$identity[$key]),
                    'source' => __('partner_governance.documents_kyc'),
                ];
            }
        }

        return $out;
    }
}
