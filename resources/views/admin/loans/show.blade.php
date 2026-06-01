<x-admin.layout
    title="Loan {{ $loan->loan_number }}"
    heading="Loan {{ $loan->loan_number }}"
    subheading="Disbursed {{ optional($loan->disbursement_date)->format('Y-m-d') ?? '—' }}">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('admin.loans.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-800">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to loans
        </a>
        <div class="ml-auto flex items-center gap-2">
            @if (in_array($loan->status, ['pending']))
                <form method="POST" action="{{ route('admin.loans.disburse', $loan) }}"
                      onsubmit="return confirm('Disburse this loan? Fees will be auto-charged from charges_fees config.');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg shadow-sm transition">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Disburse
                    </button>
                </form>
            @endif
            @if (in_array($loan->status, ['active', 'defaulted']) && (float) $loan->outstanding_balance > 0)
                <a href="{{ route('admin.loans.write-off-form', $loan) }}"
                   class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg shadow-sm transition">
                    Write off
                </a>
            @endif
            <a href="{{ route('admin.loans.edit', $loan) }}"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg shadow-sm transition">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Summary card --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <div class="text-xs uppercase tracking-wider text-gray-500">Outstanding balance</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">
                        TZS {{ number_format((float) $loan->outstanding_balance) }}
                    </div>
                </div>
                <span @class([
                    'inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold uppercase tracking-wider',
                    'bg-emerald-100 text-emerald-800' => $loan->status === 'active',
                    'bg-red-100 text-red-800'         => in_array($loan->status, ['defaulted', 'written_off']),
                    'bg-amber-100 text-amber-800'     => $loan->status === 'pending',
                    'bg-gray-100 text-gray-700'       => $loan->status === 'closed',
                ])>
                    {{ display_label($loan->status, 'loan_status') }}
                </span>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">Loan number</dt>
                    <dd class="font-mono text-gray-900">{{ $loan->loan_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Customer</dt>
                    <dd class="text-gray-900">
                        {{ trim(($loan->customer?->first_name ?? '').' '.($loan->customer?->last_name ?? '')) ?: '—' }}
                        @if ($loan->customer?->phone)
                            <div class="text-xs text-gray-500">{{ $loan->customer->phone }}</div>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Loan product</dt>
                    <dd class="text-gray-900">{{ $loan->product?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Linked application</dt>
                    <dd class="text-gray-900">
                        @if ($loan->application)
                            <a href="{{ route('admin.loan-applications.show', $loan->application) }}" class="font-mono text-amber-700 hover:text-amber-800">
                                {{ $loan->application->application_number }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Principal amount</dt>
                    <dd class="text-gray-900">TZS {{ number_format((float) $loan->principal_amount) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Approved amount</dt>
                    <dd class="text-gray-900">TZS {{ number_format((float) $loan->approved_amount) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Interest rate</dt>
                    <dd class="text-gray-900">{{ number_format((float) $loan->interest_rate * 100, 2) }}%</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Tenure</dt>
                    <dd class="text-gray-900">{{ $loan->tenure_months }} months</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Disbursement date</dt>
                    <dd class="text-gray-900">{{ optional($loan->disbursement_date)->format('Y-m-d') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Maturity date</dt>
                    <dd class="text-gray-900">{{ optional($loan->maturity_date)->format('Y-m-d') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Next due date</dt>
                    <dd class="text-gray-900">{{ optional($loan->next_due_date)->format('Y-m-d') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Closed at</dt>
                    <dd class="text-gray-900">{{ optional($loan->closed_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Meta card --}}
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Audit</h3>
            <dl class="space-y-3 text-sm">
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

    {{-- Fees applied at disbursement --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">Fees &amp; Charges</h3>
            <div class="text-sm text-gray-600">
                Net disbursed:
                <span class="font-semibold text-gray-900">TZS {{ number_format((float) ($loan->net_disbursed_amount ?? max(0, (float)$loan->approved_amount - (float)$loan->fees_total)) ) }}</span>
                <span class="text-xs text-gray-500">(approved − fees)</span>
            </div>
        </div>
        @if ($loan->fees->isEmpty())
            <p class="text-sm text-gray-500">No fees applied yet. Fees are auto-charged on disbursement from <a class="text-amber-700 underline" href="{{ route('admin.charges-fees.index') }}">Charges &amp; Fees</a>.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="text-left py-2 pr-4">Code</th>
                            <th class="text-left py-2 pr-4">Name</th>
                            <th class="text-left py-2 pr-4">Basis</th>
                            <th class="text-right py-2 pr-4">Rate / Amount</th>
                            <th class="text-right py-2 pr-4">Computed (TZS)</th>
                            <th class="text-left py-2 pr-4">Status</th>
                            <th class="text-left py-2">Charged</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($loan->fees as $fee)
                            <tr>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $fee->code }}</td>
                                <td class="py-2 pr-4">{{ $fee->name }}</td>
                                <td class="py-2 pr-4 text-xs text-gray-600">{{ $fee->basis }}</td>
                                <td class="py-2 pr-4 text-right">{{ $fee->basis === 'percentage' ? rtrim(rtrim(number_format((float) $fee->rate_or_amount, 4), '0'), '.').'%' : number_format((float) $fee->rate_or_amount) }}</td>
                                <td class="py-2 pr-4 text-right font-semibold">{{ number_format((float) $fee->computed_amount) }}</td>
                                <td class="py-2 pr-4 capitalize text-xs">{{ $fee->status }}</td>
                                <td class="py-2 text-xs text-gray-500">{{ optional($fee->charged_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200">
                            <td colspan="4" class="py-2 pr-4 text-right text-xs uppercase text-gray-500">Total fees</td>
                            <td class="py-2 pr-4 text-right font-bold">{{ number_format((float) $loan->fees_total) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    {{-- Repayment schedule --}}
    @php
        $schedule  = $loan->repaymentSchedules;
        $sumPrin   = (float) $schedule->sum('principal_due');
        $sumInt    = (float) $schedule->sum('interest_due');
        $sumTotal  = (float) $schedule->sum('total_due');
        $sumPaid   = (float) $schedule->sum('amount_paid');
        $today     = \Carbon\Carbon::today();
    @endphp
    <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">Repayment schedule</h3>
            <div class="text-xs text-gray-500">
                {{ $schedule->count() }} installments · Paid TZS {{ number_format($sumPaid) }} / {{ number_format($sumTotal) }}
            </div>
        </div>

        @if ($schedule->isEmpty())
            <p class="text-sm text-gray-500">
                No schedule generated yet. It is built automatically when a loan is disbursed.
                For legacy loans, run <code class="px-1.5 py-0.5 bg-gray-100 rounded text-xs">php artisan loans:backfill-schedules</code>.
            </p>
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
                                if (! in_array($row->status, ['paid']) && $row->due_date && \Carbon\Carbon::parse($row->due_date)->lt($today) && (float)$row->amount_paid < (float)$row->total_due) {
                                    $effectiveStatus = 'overdue';
                                }
                            @endphp
                            <tr>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $row->installment_no }}</td>
                                <td class="py-2 pr-4">{{ \Carbon\Carbon::parse($row->due_date)->format('Y-m-d') }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format((float) $row->principal_due) }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format((float) $row->interest_due) }}</td>
                                <td class="py-2 pr-4 text-right font-semibold">{{ number_format((float) $row->total_due) }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format((float) $row->amount_paid) }}</td>
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
                            <td class="py-2 pr-4 text-right font-bold">{{ number_format($sumPrin) }}</td>
                            <td class="py-2 pr-4 text-right font-bold">{{ number_format($sumInt) }}</td>
                            <td class="py-2 pr-4 text-right font-bold">{{ number_format($sumTotal) }}</td>
                            <td class="py-2 pr-4 text-right font-bold">{{ number_format($sumPaid) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</x-admin.layout>