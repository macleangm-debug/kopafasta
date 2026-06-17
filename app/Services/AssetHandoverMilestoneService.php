<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\VendorTask;

class AssetHandoverMilestoneService
{
    /** @return array<string, mixed>|null */
    public function forApplication(LoanApplication $application): ?array
    {
        if (! app(AssetLendingService::class)->isAssetLendingApplication($application)) {
            return null;
        }

        $reservation = app(AssetReservationService::class)->reservationForApplication($application);
        if (! $reservation) {
            return null;
        }

        $readiness = app(ApplicationDisbursementReadinessService::class);
        $showAfterOffer = $readiness->offerSigned($application)
            || in_array($application->status, ['approved', 'disbursement', 'disbursed', 'closed'], true);

        if (! $showAfterOffer) {
            return null;
        }

        $reservation->loadMissing('asset');
        $asset = $reservation->asset;
        $category = $asset?->category;
        $reqs = app(AssetLendingService::class)->categoryRequirements($category);
        $gpsRequired = (bool) ($reqs['gps_required'] ?? false);
        $insuranceRequired = (bool) ($reqs['insurance_required'] ?? false);
        $registrationRequired = (bool) ($reqs['ownership_transfer_required'] ?? false);

        $status = (string) $reservation->status;
        $assetReady = $readiness->contractSigned($application)
            && (! $readiness->hasPostApprovalFees($application) || $readiness->feesPaid($application));

        $gpsComplete = ! $gpsRequired
            || in_array($status, ['gps_installation', 'insurance_active', 'released'], true)
            || $this->gpsTaskComplete($application->id);

        $insurance = app(AssetLendingService::class)->insuranceStatus($asset?->insurance_expires_at);
        $insuranceComplete = ! $insuranceRequired
            || in_array($status, ['insurance_active', 'released'], true)
            || in_array($insurance['status'] ?? '', ['valid', 'expiring'], true);

        $registrationComplete = ! $registrationRequired
            || in_array($status, ['insurance_active', 'released'], true);

        $handoverComplete = $status === 'released';

        $definitions = [
            ['key' => 'asset_ready', 'label' => __('borrower.handover_milestones.asset_ready'), 'done' => $assetReady],
        ];

        if ($gpsRequired) {
            $definitions[] = ['key' => 'gps_installed', 'label' => __('borrower.handover_milestones.gps_installed'), 'done' => $gpsComplete];
        }

        if ($insuranceRequired) {
            $definitions[] = ['key' => 'insurance_active', 'label' => __('borrower.handover_milestones.insurance_active'), 'done' => $insuranceComplete];
        }

        if ($registrationRequired) {
            $definitions[] = ['key' => 'registration_complete', 'label' => __('borrower.handover_milestones.registration_complete'), 'done' => $registrationComplete];
        }

        $definitions[] = ['key' => 'asset_handed_over', 'label' => __('borrower.handover_milestones.asset_handed_over'), 'done' => $handoverComplete];

        $currentIndex = collect($definitions)->search(fn (array $row) => ! $row['done']);
        if ($currentIndex === false) {
            $currentIndex = count($definitions) - 1;
        }

        $milestones = collect($definitions)->values()->map(function (array $row, int $index) use ($currentIndex, $insurance, $status, $handoverComplete) {
            $state = $row['done']
                ? 'completed'
                : ($index === $currentIndex ? 'in_progress' : 'pending');

            $detail = match ($row['key']) {
                'insurance_active' => filled($insurance['label'] ?? null) ? (string) $insurance['label'] : null,
                'asset_handed_over' => $handoverComplete ? __('borrower.handover_milestones.handed_over_detail') : null,
                default => null,
            };

            return [
                'key'    => $row['key'],
                'label'  => $row['label'],
                'status' => $state,
                'detail' => $detail,
            ];
        })->all();

        return [
            'asset_title' => $asset?->title ?? __('borrower.handover_milestones.asset'),
            'milestones'  => $milestones,
            'complete'    => $handoverComplete,
        ];
    }

    private function gpsTaskComplete(int $applicationId): bool
    {
        return VendorTask::query()
            ->where('loan_application_id', $applicationId)
            ->where('status', 'completed')
            ->where('task_type', 'like', '%gps%')
            ->exists();
    }
}
