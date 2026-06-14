<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Carbon;

class LoanServicingTimelineService
{
    public function __construct(
        private readonly ActiveLoanServicingService $servicing,
    ) {}

    /** @return list<array{key: string, label: string, detail: ?string, at: \Illuminate\Support\Carbon, tone: string}> */
    public function forLoan(Loan $loan): array
    {
        $loan->loadMissing(['repayments', 'restructureRequests', 'topUpRequests', 'application', 'repaymentSchedules']);

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

        foreach ($loan->repayments()->whereIn('status', ['received', 'allocated'])->orderBy('paid_at')->get() as $payment) {
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

        foreach ($loan->repaymentSchedules as $row) {
            if (! $this->servicing->isOverdue($row) || ! $row->due_date) {
                continue;
            }

            $events[] = [
                'key'    => 'missed_payment',
                'label'  => __('borrower.loan_servicing.timeline.missed_payment'),
                'detail' => __('borrower.loan_servicing.timeline.missed_payment_detail', [
                    'installment' => $row->installment_no,
                    'amount'      => format_money((float) $row->total_due),
                ]),
                'at'     => $row->due_date->copy()->addDay()->startOfDay(),
                'tone'   => 'red',
            ];
        }

        foreach ($loan->restructureRequests as $request) {
            $events[] = [
                'key'    => 'restructure_requested',
                'label'  => __('borrower.loan_servicing.timeline.restructure_requested'),
                'detail' => str_replace('_', ' ', $request->restructure_type),
                'at'     => $request->created_at ?? now(),
                'tone'   => 'amber',
            ];

            if ($request->status === 'approved' && $request->approved_at) {
                $events[] = [
                    'key'    => 'restructure_approved',
                    'label'  => __('borrower.loan_servicing.timeline.restructure_approved'),
                    'detail' => str_replace('_', ' ', $request->restructure_type),
                    'at'     => $request->approved_at,
                    'tone'   => 'emerald',
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

        foreach ($loan->topUpRequests as $request) {
            if ($request->status === 'approved' && $request->reviewed_at) {
                $events[] = [
                    'key'    => 'top_up_approved',
                    'label'  => __('borrower.loan_servicing.timeline.top_up_approved'),
                    'detail' => format_money((float) $request->requested_amount),
                    'at'     => $request->reviewed_at,
                    'tone'   => 'amber',
                ];
            }

            if ($request->status === 'disbursed' && $request->disbursed_at) {
                $events[] = [
                    'key'    => 'top_up_disbursed',
                    'label'  => __('borrower.loan_servicing.timeline.top_up_disbursed'),
                    'detail' => format_money((float) $request->requested_amount),
                    'at'     => $request->disbursed_at,
                    'tone'   => 'emerald',
                ];
            }
        }

        if ($loan->status === 'arrears') {
            $firstOverdue = $loan->repaymentSchedules
                ->filter(fn ($row) => $this->servicing->isOverdue($row))
                ->sortBy('due_date')
                ->first();

            if ($firstOverdue?->due_date) {
                $events[] = [
                    'key'    => 'arrears',
                    'label'  => __('borrower.loan_servicing.timeline.arrears'),
                    'detail' => __('borrower.loan_servicing.in_arrears'),
                    'at'     => $firstOverdue->due_date->copy()->addDay()->startOfDay(),
                    'tone'   => 'red',
                ];
            }
        }

        if ($loan->status === 'closed') {
            $closedAt = $loan->closed_at ?? $loan->updated_at;

            $events[] = [
                'key'    => 'closed',
                'label'  => __('borrower.loan_servicing.timeline.closed'),
                'detail' => null,
                'at'     => Carbon::parse($closedAt),
                'tone'   => 'gray',
            ];
        }

        usort($events, fn ($a, $b) => $b['at']->timestamp <=> $a['at']->timestamp);

        return $events;
    }
}
