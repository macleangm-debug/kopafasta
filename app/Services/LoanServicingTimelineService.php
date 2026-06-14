<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Carbon;

class LoanServicingTimelineService
{
    /** @return list<array{key: string, label: string, detail: ?string, at: \Illuminate\Support\Carbon, tone: string}> */
    public function forLoan(Loan $loan): array
    {
        $loan->loadMissing(['repayments', 'restructureRequests', 'application']);

        $events = [];

        if ($loan->disbursement_date) {
            $events[] = [
                'key'    => 'disbursed',
                'label'  => __('borrower.loan_servicing.timeline.disbursed'),
                'detail' => format_money((float) $loan->principal_amount),
                'at'     => Carbon::parse($loan->disbursement_date)->startOfDay(),
                'tone'   => 'emerald',
            ];
        }

        foreach ($loan->repayments()->where('status', 'received')->orderBy('paid_at')->get() as $payment) {
            if (! $payment->paid_at) {
                continue;
            }

            $events[] = [
                'key'    => 'payment',
                'label'  => __('borrower.loan_servicing.timeline.payment'),
                'detail' => format_money((float) $payment->amount).' · '.($payment->reference ?? ''),
                'at'     => $payment->paid_at,
                'tone'   => 'sky',
            ];
        }

        foreach ($loan->restructureRequests as $request) {
            if ($request->status === 'approved' && $request->approved_at) {
                $events[] = [
                    'key'    => 'restructure_approved',
                    'label'  => __('borrower.loan_servicing.timeline.restructure_approved'),
                    'detail' => str_replace('_', ' ', $request->restructure_type),
                    'at'     => $request->approved_at,
                    'tone'   => 'amber',
                ];
            }

            if ($request->status === 'rejected' && $request->approved_at) {
                $events[] = [
                    'key'    => 'restructure_rejected',
                    'label'  => __('borrower.loan_servicing.timeline.restructure_rejected'),
                    'detail' => null,
                    'at'     => $request->approved_at,
                    'tone'   => 'gray',
                ];
            }
        }

        if ($loan->status === 'arrears') {
            $firstOverdue = $loan->repaymentSchedules
                ->filter(fn ($row) => app(ActiveLoanServicingService::class)->isOverdue($row))
                ->sortBy('due_date')
                ->first();

            if ($firstOverdue?->due_date) {
                $events[] = [
                    'key'    => 'arrears',
                    'label'  => __('borrower.loan_servicing.timeline.arrears'),
                    'detail' => __('borrower.loan_servicing.in_arrears'),
                    'at'     => $firstOverdue->due_date->startOfDay(),
                    'tone'   => 'red',
                ];
            }
        }

        usort($events, fn ($a, $b) => $b['at']->timestamp <=> $a['at']->timestamp);

        return $events;
    }
}
