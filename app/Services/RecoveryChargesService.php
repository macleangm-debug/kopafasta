<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanFee;
use App\Models\RecoveryAssignment;

class RecoveryChargesService
{
    public function __construct(
        private readonly RecoveryPolicyService $policy,
    ) {}

    /**
     * Recovery charges are calculated from original outstanding at assignment — not compounded.
     *
     * @return array{
     *     total: float,
     *     partner_total: float,
     *     company_total: float,
     *     items: list<array{
     *         partner_type: string,
     *         label: string,
     *         partner_amount: float,
     *         company_amount: float,
     *         total: float,
     *         assigned_at: ?\Illuminate\Support\Carbon,
     *         status: string
     *     }>
     * }
     */
    public function breakdownForLoan(Loan $loan): array
    {
        $assignments = RecoveryAssignment::query()
            ->whereHas('arrearCase', fn ($q) => $q->where('loan_id', $loan->id))
            ->orderBy('assigned_at')
            ->get();

        $items = $assignments->map(function (RecoveryAssignment $assignment): array {
            $partnerAmount = (float) $assignment->commission_earned;
            $total = (float) $assignment->recovery_charge;
            $companyAmount = round(max(0, $total - $partnerAmount), 2);

            return [
                'partner_type'   => $assignment->partner_type,
                'label'          => $this->policy->partnerTypeLabel($assignment->partner_type),
                'partner_amount' => $partnerAmount,
                'company_amount' => $companyAmount,
                'total'          => $total,
                'assigned_at'    => $assignment->assigned_at,
                'status'         => $assignment->status,
            ];
        })->values()->all();

        if ($items === []) {
            $items = $this->itemsFromUnpaidFees($loan);
        }

        $partnerTotal = round(collect($items)->sum('partner_amount'), 2);
        $companyTotal = round(collect($items)->sum('company_amount'), 2);

        return [
            'total'          => round($partnerTotal + $companyTotal, 2),
            'partner_total'  => $partnerTotal,
            'company_total'  => $companyTotal,
            'items'          => $items,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function itemsFromUnpaidFees(Loan $loan): array
    {
        return $this->unpaidRecoveryFeesQuery($loan)
            ->get()
            ->map(function (LoanFee $fee): array {
                $partnerType = str_starts_with((string) $fee->code, 'RECOVERY_')
                    ? substr((string) $fee->code, 9)
                    : 'recovery';
                $total = (float) $fee->computed_amount;

                return [
                    'partner_type'   => $partnerType,
                    'label'          => $this->policy->partnerTypeLabel($partnerType),
                    'partner_amount' => $total,
                    'company_amount' => 0.0,
                    'total'          => $total,
                    'assigned_at'    => $fee->charged_at,
                    'status'         => 'charged',
                ];
            })
            ->values()
            ->all();
    }

    private function unpaidRecoveryFeesQuery(Loan $loan)
    {
        return $loan->fees()
            ->whereNull('paid_at')
            ->where(function ($query): void {
                $query->whereIn('code', ['RECOVERY', 'LEGAL', 'COLLECTION'])
                    ->orWhere('code', 'like', 'RECOVERY\_%')
                    ->orWhere('type', 'recovery');
            });
    }
}
