<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanApplicationPostApprovalFee;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Disbursement SLA (standard working days) + optional paid fast-track after offer acceptance.
 */
class DisbursementSlaService
{
    public const FEE_CODE = 'FAST_TRACK';

    public function __construct(
        private readonly UnderwritingSettingsService $settings,
        private readonly WorkingCalendarService $calendar,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->disbursementFastTrackEnabled()
            && $this->settings->disbursementFastTrackFeeAmount() > 0;
    }

    public function feeAmount(): float
    {
        return $this->settings->disbursementFastTrackFeeAmount();
    }

    public function businessHours(): int
    {
        return $this->settings->disbursementFastTrackBusinessHours();
    }

    public function standardWorkingDays(): int
    {
        return $this->settings->disbursementSlaWorkingDays();
    }

    public function isOptedIn(LoanApplication $application): bool
    {
        return (bool) data_get($application->screening_payload, 'disbursement.fast_track_opted_in', false)
            || $this->hasPendingOrPaidFastTrackFee($application);
    }

    public function isPaid(LoanApplication $application): bool
    {
        $fee = $this->fastTrackFee($application);

        return $fee?->isPaid() ?? false;
    }

    public function fastTrackFee(LoanApplication $application): ?LoanApplicationPostApprovalFee
    {
        return LoanApplicationPostApprovalFee::query()
            ->where('loan_application_id', $application->id)
            ->whereRaw('UPPER(code) = ?', [self::FEE_CODE])
            ->orderByDesc('id')
            ->first();
    }

    public function hasPendingOrPaidFastTrackFee(LoanApplication $application): bool
    {
        $fee = $this->fastTrackFee($application);

        return $fee && in_array($fee->status, ['pending', 'paid'], true);
    }

    /**
     * Borrower opts into fast-track on the post-approval fees page (creates/removes fee line).
     *
     * @return array{opted_in: bool, fee: ?LoanApplicationPostApprovalFee}
     */
    public function setOptIn(LoanApplication $application, bool $optIn): array
    {
        abort_unless($this->enabled(), 422, 'Fast-track disbursement is not enabled.');

        $payload = $application->screening_payload ?? [];
        $disbursement = is_array($payload['disbursement'] ?? null) ? $payload['disbursement'] : [];
        $disbursement['fast_track_opted_in'] = $optIn;
        $disbursement['fast_track_opted_at'] = $optIn ? now()->toIso8601String() : null;
        $payload['disbursement'] = $disbursement;
        $application->update(['screening_payload' => $payload]);

        $fee = $this->fastTrackFee($application);

        if ($optIn) {
            if ($fee && $fee->isPaid()) {
                return ['opted_in' => true, 'fee' => $fee];
            }
            if ($fee && $fee->status === 'pending') {
                $fee->update([
                    'name' => __('borrower.post_approval_fees.fast_track_name'),
                    'calculated_amount' => $this->feeAmount(),
                    'configured_amount' => $this->feeAmount(),
                ]);

                return ['opted_in' => true, 'fee' => $fee->fresh()];
            }

            $fee = LoanApplicationPostApprovalFee::create([
                'loan_application_id' => $application->id,
                'code' => self::FEE_CODE,
                'name' => __('borrower.post_approval_fees.fast_track_name'),
                'fee_type' => 'fixed',
                'configured_amount' => $this->feeAmount(),
                'calculated_amount' => $this->feeAmount(),
                'status' => 'pending',
            ]);

            return ['opted_in' => true, 'fee' => $fee];
        }

        if ($fee && $fee->status === 'pending') {
            $fee->delete();
            $fee = null;
        }

        return ['opted_in' => false, 'fee' => $fee];
    }

    /** Start (or refresh) the disbursement SLA clock when the loan contract is signed. */
    public function startClockOnContractSigned(LoanApplication $application): void
    {
        $payload = $application->screening_payload ?? [];
        $disbursement = is_array($payload['disbursement'] ?? null) ? $payload['disbursement'] : [];
        $fast = $this->isPaid($application);
        $startedAt = now();
        $dueAt = $fast
            ? $this->addBusinessHours($startedAt, $this->businessHours())
            : $this->addWorkingDays($startedAt, $this->standardWorkingDays());

        $disbursement['sla_started_at'] = $startedAt->toIso8601String();
        $disbursement['sla_due_at'] = $dueAt->toIso8601String();
        $disbursement['sla_mode'] = $fast ? 'fast_track' : 'standard';
        $disbursement['sla_label'] = $fast
            ? __('borrower.post_approval_fees.fast_track_sla_hours', ['hours' => $this->businessHours()])
            : __('borrower.post_approval_fees.standard_sla_days', ['days' => $this->standardWorkingDays()]);

        $payload['disbursement'] = $disbursement;
        $application->update(['screening_payload' => $payload]);
    }

    /** @return array<string, mixed> */
    public function viewModel(LoanApplication $application): array
    {
        $enabled = $this->enabled();
        $optedIn = $this->isOptedIn($application);
        $paid = $this->isPaid($application);
        $fee = $this->fastTrackFee($application);

        return [
            'enabled' => $enabled,
            'opted_in' => $optedIn,
            'paid' => $paid,
            'fee_amount' => $this->feeAmount(),
            'business_hours' => $this->businessHours(),
            'standard_working_days' => $this->standardWorkingDays(),
            'fee_pending' => $fee && $fee->status === 'pending',
            'sla_due_at' => data_get($application->screening_payload, 'disbursement.sla_due_at'),
            'sla_mode' => data_get($application->screening_payload, 'disbursement.sla_mode'),
            'sla_label' => data_get($application->screening_payload, 'disbursement.sla_label'),
        ];
    }

    public function addWorkingDays(CarbonInterface $from, int $days): Carbon
    {
        return $this->calendar->addWorkingDays($from, $days);
    }

    public function addBusinessHours(CarbonInterface $from, int $hours): Carbon
    {
        return $this->calendar->addWorkingHours($from, $hours);
    }
}
