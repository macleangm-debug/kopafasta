@php
    $loan = $linkedLoan;
    $servicing = $loan ? app(\App\Services\ActiveLoanServicingService::class)->forLoan($loan) : null;
    $arrearCase = $loan
        ? \App\Models\ArrearCase::query()
            ->where('loan_id', $loan->id)
            ->where('status', 'open')
            ->with([
                'actions' => fn ($q) => $q->with('performer')->latest('performed_at')->limit(10),
                'recoveryAssignments' => fn ($q) => $q->with('vendor')
                    ->whereIn('status', ['assigned', 'in_progress'])
                    ->latest('id'),
            ])
            ->latest('id')
            ->first()
        : null;
    $collectionActions = $arrearCase?->actions ?? collect();
    $recentRepayments = $loan ? $loan->repayments()->latest('paid_at')->limit(8)->get() : collect();
    $restructureRequests = $loan ? $loan->restructureRequests()->latest()->limit(5)->get() : collect();
    $topUpRequests = $loan ? $loan->topUpRequests()->latest()->limit(5)->get() : collect();
    $activeRecovery = null;
    $collateralGps = $loan ? app(\App\Services\GpsDeviceService::class)->collateralForLoan($loan) : [];
    $gpsInstallerContact = $loan ? app(\App\Services\GpsDeviceService::class)->installerContactForLoan($loan) : null;
    $auctionHold = $loan ? app(\App\Services\AuctionHoldService::class)->statusForLoan($loan) : null;
    if ($loan) {
        $loan->loadMissing(['fees', 'repaymentSchedules', 'customer', 'product']);
    }
    $schedule = $loan?->repaymentSchedules ?? collect();
    $sumPrin = (float) $schedule->sum('principal_due');
    $sumInt = (float) $schedule->sum('interest_due');
    $sumTotal = (float) $schedule->sum('total_due');
    $sumPaid = (float) $schedule->sum('amount_paid');
    $today = \Carbon\Carbon::today();
@endphp

@if (! $loan)
    <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-950">
        No loan record is linked to this credit file yet.
    </div>
@else
    <div class="grid lg:grid-cols-12 gap-4">
        <div class="lg:col-span-8 space-y-4">
            <x-loan-balance-breakdown
                :breakdown="$servicing['balance_breakdown'] ?? app(\App\Services\LoanBalanceService::class)->breakdown($loan)"
                :recovery-charges="app(\App\Services\RecoveryChargesService::class)->breakdownForLoan($loan)"
                :expanded="true"
            />

            <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Loan number</dt>
                    <dd class="font-mono font-semibold text-gray-900 mt-1">{{ $loan->loan_number }}</dd>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Disbursed</dt>
                    <dd class="font-semibold text-gray-900 mt-1">{{ optional($loan->disbursement_date ?? $record->disbursed_at)->format('d M Y') ?? '—' }}</dd>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Principal</dt>
                    <dd class="font-semibold text-gray-900 mt-1">{{ format_money((float) $loan->principal_amount) }}</dd>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Tenure</dt>
                    <dd class="font-semibold text-gray-900 mt-1">{{ $loan->tenure_months }} months</dd>
                </div>
            </dl>
        </div>
        <div class="lg:col-span-4 rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">File record</p>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">Application</dt>
                    <dd class="font-mono text-gray-900">{{ $record->application_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Created</dt>
                    <dd class="text-gray-900">{{ $loan->created_at?->format('Y-m-d H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Last updated</dt>
                    <dd class="text-gray-900">{{ $loan->updated_at?->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>
        </div>
    </div>

    @include('admin.loans._servicing')

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">Repayment schedule</h3>
            <div class="text-xs text-gray-500">
                {{ $schedule->count() }} installments · Paid {{ format_money($sumPaid) }} / {{ format_number($sumTotal) }}
            </div>
        </div>
        @if ($schedule->isEmpty())
            <p class="text-sm text-gray-500">No schedule on file yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="text-left py-2 pr-4">#</th>
                            <th class="text-left py-2 pr-4">Due date</th>
                            <th class="text-right py-2 pr-4">Principal</th>
                            <th class="text-right py-2 pr-4">Interest</th>
                            <th class="text-right py-2 pr-4">Total due</th>
                            <th class="text-right py-2 pr-4">Paid</th>
                            <th class="text-left py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($schedule as $row)
                            @php
                                $effectiveStatus = $row->status;
                                if (! in_array($row->status, ['paid']) && $row->due_date && \Carbon\Carbon::parse($row->due_date)->lt($today) && (float) $row->amount_paid < (float) $row->total_due) {
                                    $effectiveStatus = 'overdue';
                                }
                            @endphp
                            <tr>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $row->installment_no }}</td>
                                <td class="py-2 pr-4">{{ optional($row->due_date)->format('Y-m-d') }}</td>
                                <td class="py-2 pr-4 text-right">{{ format_number((float) $row->principal_due) }}</td>
                                <td class="py-2 pr-4 text-right">{{ format_number((float) $row->interest_due) }}</td>
                                <td class="py-2 pr-4 text-right font-semibold">{{ format_number((float) $row->total_due) }}</td>
                                <td class="py-2 pr-4 text-right">{{ format_number((float) $row->amount_paid) }}</td>
                                <td class="py-2">
                                    <span @class([
                                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase',
                                        'bg-emerald-100 text-emerald-800' => $effectiveStatus === 'paid',
                                        'bg-amber-100 text-amber-800'     => $effectiveStatus === 'partial',
                                        'bg-red-100 text-red-800'         => $effectiveStatus === 'overdue',
                                        'bg-gray-100 text-gray-700'       => $effectiveStatus === 'pending',
                                    ])>{{ $effectiveStatus }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200">
                            <td colspan="2" class="py-2 pr-4 text-right text-xs uppercase text-gray-500">Totals</td>
                            <td class="py-2 pr-4 text-right font-bold">{{ format_number($sumPrin) }}</td>
                            <td class="py-2 pr-4 text-right font-bold">{{ format_number($sumInt) }}</td>
                            <td class="py-2 pr-4 text-right font-bold">{{ format_number($sumTotal) }}</td>
                            <td class="py-2 pr-4 text-right font-bold">{{ format_number($sumPaid) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
@endif
