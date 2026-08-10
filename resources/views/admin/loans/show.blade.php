<x-admin.layout
    title="Loan {{ $loan->loan_number }}"
    heading="Loan {{ $loan->loan_number }}"
    subheading="Disbursed {{ optional($loan->disbursement_date)->format('Y-m-d') ?? '—' }}">

@if (! empty($disbursementBlocking) && $loan->status === 'pending')
            <div class="mb-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Disbursement blocked</p>
                <ul class="mt-1 list-disc list-inside text-amber-800">
                    @foreach ($disbursementBlocking as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($disbursementChecklist) && $loan->status === 'pending')
            <div class="mb-4 rounded-lg ring-1 ring-gray-200 overflow-hidden bg-white">
                <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-600">
                        {{ $loan->application?->application_number ?? $loan->loan_number }} — Disbursement prerequisites
                    </p>
                </div>
                <div class="px-4 py-4 grid lg:grid-cols-2 gap-4 border-b border-gray-100">
                    <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">Loan reference</dt>
                            <dd class="font-mono font-semibold text-gray-900">{{ $loan->loan_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Approved amount</dt>
                            <dd class="font-semibold text-gray-900">{{ format_money((float) $loan->approved_amount) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Post-approval fee</dt>
                            <dd class="font-semibold {{ ($disbursementChecklist['post_approval_fee']['complete'] ?? false) ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ ($disbursementChecklist['post_approval_fee']['complete'] ?? false) ? 'Paid' : 'Pending' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Contract</dt>
                            <dd class="font-semibold {{ ($disbursementChecklist['contract']['complete'] ?? false) ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ ($disbursementChecklist['contract']['complete'] ?? false) ? 'Accepted' : 'Pending' }}
                            </dd>
                        </div>
                    </dl>
                    @if (! empty($disbursementDestination['method'] ?? null))
                        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Disbursement details</p>
                            <dl class="space-y-2">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-gray-500">Method</dt>
                                    <dd class="font-semibold text-gray-900">{{ $disbursementDetailsService->methodLabel($disbursementDestination['method'] ?? null) }}</dd>
                                </div>
                                @foreach ($disbursementDetailsService->displayLines($disbursementDestination) as $label => $value)
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-gray-500">{{ $label }}</dt>
                                        <dd class="font-semibold text-gray-900 text-right">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($disbursementChecklist as $item)
                        @php
                            $statusText = match ($item['status']) {
                                'paid' => '✓ Paid',
                                'accepted' => '✓ Accepted',
                                'complete' => '✓ Complete',
                                'not_required' => '✓ N/A',
                                'available' => '✓ Available',
                                'insufficient' => 'Insufficient',
                                'pending' => 'Pending',
                                'locked' => 'Locked',
                                'not_generated' => 'Not generated',
                                default => ucfirst($item['status']),
                            };
                            $tone = ($item['complete'] ?? false) ? 'text-emerald-700' : (($item['status'] ?? '') === 'locked' ? 'text-gray-500' : 'text-amber-700');
                        @endphp
                        <li class="px-4 py-3 flex items-center justify-between text-sm">
                            <span class="font-medium text-gray-900">{{ $item['label'] }}</span>
                            <span class="font-semibold {{ $tone }}">{{ $statusText }}</span>
                        </li>
                    @endforeach
                </ul>
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
                @if ($canDisburse ?? true)
                    <form method="POST" action="{{ route('admin.loans.disburse', $loan) }}"
                          @submit.prevent="window.confirmForm($el, {
                              title: @js(__('admin.confirm.loan_disburse_title')),
                              message: @js(__('admin.confirm.loan_disburse_message')),
                              confirmLabel: @js(__('admin.confirm.loan_disburse_confirm')),
                              confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                              tone: 'confirm',
                          })">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg shadow-sm transition">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Disburse Loan
                        </button>
                    </form>
                @else
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-500 bg-gray-100 px-4 py-2 rounded-lg cursor-not-allowed"
                          title="{{ implode(' ', $disbursementBlocking ?? []) }}">
                        Disburse locked
                    </span>
                @endif
            @endif
            @if ($loan->status === 'active')
                @if ($canReverseDisbursement ?? false)
                    <details class="relative">
                        <summary class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded-lg shadow-sm transition cursor-pointer list-none">
                            Reverse disbursement
                        </summary>
                        <form method="POST" action="{{ route('admin.loans.reverse-disbursement', $loan) }}"
                              class="absolute right-0 z-10 mt-2 w-80 rounded-xl border border-gray-200 bg-white p-4 shadow-lg"
                              @submit.prevent="window.confirmForm($el, {
                                  title: @js(__('admin.confirm.loan_reverse_disbursement_title')),
                                  message: @js(__('admin.confirm.loan_reverse_disbursement_message')),
                                  confirmLabel: @js(__('admin.confirm.loan_reverse_disbursement_confirm')),
                                  confirmClass: 'bg-orange-600 hover:bg-orange-700 text-white',
                                  tone: 'warning',
                              })">
                            @csrf
                            <p class="text-sm text-gray-600 mb-3">Capital allocation and schedules will be rolled back. The application returns to the disbursement queue.</p>
                            <label for="reverse-reason" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Reason</label>
                            <textarea id="reverse-reason" name="reason" rows="3" required maxlength="500"
                                      class="w-full rounded-lg border-gray-300 text-sm mb-3"
                                      placeholder="e.g. Disbursed in error — borrower not ready"></textarea>
                            <button type="submit"
                                    class="w-full inline-flex justify-center items-center gap-1.5 text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded-lg transition">
                                Confirm reversal
                            </button>
                        </form>
                    </details>
                @elseif (! empty($reverseBlocking))
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-500 bg-gray-100 px-4 py-2 rounded-lg cursor-not-allowed"
                          title="{{ implode(' ', $reverseBlocking) }}">
                        Cannot reverse
                    </span>
                @endif
            @endif
            @if (in_array($loan->status, ['active', 'arrears', 'defaulted']) && (float) $loan->outstanding_balance > 0)
                @php
                    $writeOffApprovalRequired = (bool) \App\Models\Setting::get('finance.write_off_approval_required');
                    $writeOffService = app(\App\Services\WriteOffRequestService::class);
                    $canRecommendWriteOff = $writeOffApprovalRequired
                        && $writeOffService->canRecommend(auth()->user())
                        && ! $writeOffService->hasOpenRequest($loan);
                @endphp
                @if ($writeOffApprovalRequired)
                    @if ($canRecommendWriteOff)
                        <a href="{{ route('admin.loans.write-off-form', $loan) }}"
                           class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg shadow-sm transition">
                            Recommend write-off
                        </a>
                    @endif
                    <a href="{{ route('admin.write-off-requests.index') }}"
                       class="inline-flex items-center gap-1.5 text-sm font-semibold text-red-800 bg-red-100 hover:bg-red-200 px-4 py-2 rounded-lg transition">
                        Write-off queue
                    </a>
                @else
                    <a href="{{ route('admin.loans.write-off-form', $loan) }}"
                       class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg shadow-sm transition">
                        Write off
                    </a>
                @endif
            @endif
            @if (! $loan->isServicingLocked())
            <a href="{{ route('admin.loans.edit', $loan) }}"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-lg shadow-sm transition">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Summary card --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="flex items-start justify-between mb-4 gap-4">
                <div class="max-w-md flex-1">
                    <x-loan-balance-breakdown
                        :breakdown="$servicing['balance_breakdown'] ?? app(\App\Services\LoanBalanceService::class)->breakdown($loan)"
                        :recovery-charges="app(\App\Services\RecoveryChargesService::class)->breakdownForLoan($loan)"
                        :expanded="true"
                    />
                </div>
                <span @class([
                    'inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold uppercase tracking-wider',
                    'bg-emerald-100 text-emerald-800' => $loan->status === 'active',
                    'bg-red-100 text-red-800'         => in_array($loan->status, ['defaulted', 'written_off', 'arrears']),
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
                            <a href="{{ route('admin.loan-applications.show', $loan->application) }}" class="font-mono text-brand hover:text-brand-light">
                                {{ $loan->application->application_number }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Principal amount</dt>
                    <dd class="text-gray-900">{{ format_money((float) $loan->principal_amount) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Approved amount</dt>
                    <dd class="text-gray-900">{{ format_money((float) $loan->approved_amount) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Interest rate</dt>
                    <dd class="text-gray-900">{{ format_number((float) $loan->interest_rate * 100, 2) }}%</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Tenure</dt>
                    <dd class="text-gray-900">{{ $loan->tenure_months }} months</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Disbursement date</dt>
                    <dd class="text-gray-900">{{ optional($loan->disbursement_date)->format('Y-m-d') ?? '—' }}</dd>
                </div>
                @if ($loan->disbursements->isNotEmpty())
                    @php $payout = $loan->disbursements->sortByDesc('released_at')->first(); @endphp
                    <div>
                        <dt class="text-xs text-gray-500">Payout record</dt>
                        <dd class="text-gray-900 font-mono text-xs">{{ $payout->reference }} · {{ format_money($payout->amount) }}</dd>
                    </div>
                @endif
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

    @if (in_array($loan->status, ['active', 'arrears', 'defaulted', 'closed'], true))
        @include('admin.loans._servicing')
    @endif

    @include('admin.loans._manual-capital-allocation')

    @if ($loan->capitalAllocations->isNotEmpty() && ! in_array($loan->status, ['pending'], true))
        <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Capital partner funding</h3>
            <p class="text-xs text-gray-500 mb-4">Capital allocated at disbursement · interest split {{ format_number(app(\App\Services\CapitalPartnerAllocationService::class)->partnerInterestSharePercent(), 0) }}% partner / {{ format_number(app(\App\Services\CapitalPartnerAllocationService::class)->companyInterestSharePercent(), 0) }}% company</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 text-sm">
                <div class="rounded-lg bg-gray-50 px-3 py-2"><span class="text-xs text-gray-500 block">Allocated</span><span class="font-semibold">{{ format_money($capitalTotals['allocated_principal']) }}</span></div>
                <div class="rounded-lg bg-gray-50 px-3 py-2"><span class="text-xs text-gray-500 block">Outstanding exposure</span><span class="font-semibold">{{ format_money($capitalTotals['outstanding_exposure']) }}</span></div>
                <div class="rounded-lg bg-emerald-50 px-3 py-2"><span class="text-xs text-emerald-800 block">Partner interest</span><span class="font-semibold text-emerald-900">{{ format_money($capitalTotals['interest_earned_partner']) }}</span></div>
                <div class="rounded-lg bg-sky-50 px-3 py-2"><span class="text-xs text-sky-800 block">Company interest</span><span class="font-semibold text-sky-900">{{ format_money($capitalTotals['interest_earned_company']) }}</span></div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="text-left py-2 pr-4">Partner</th>
                            <th class="text-left py-2 pr-4">Pool</th>
                            <th class="text-right py-2 pr-4">Allocated</th>
                            <th class="text-right py-2 pr-4">%</th>
                            <th class="text-right py-2 pr-4">Exposure</th>
                            <th class="text-right py-2 pr-4">Partner int.</th>
                            <th class="text-right py-2">Company int.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($capitalAllocations as $row)
                            <tr>
                                <td class="py-2 pr-4">{{ $row['partner'] }}</td>
                                <td class="py-2 pr-4 text-xs text-gray-600">{{ $row['pool'] }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($row['allocated_principal']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_number($row['allocation_percent'], 2) }}%</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($row['outstanding_exposure']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($row['interest_earned_partner']) }}</td>
                                <td class="py-2 text-right font-mono">{{ format_money($row['interest_earned_company']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($loan->status === 'pending' && $loan->product && ($loan->product->uses_capital_partner ?? true) && ! ($needsManualCapitalAllocation ?? false) && $loan->capitalAllocations->isEmpty() && ! ($loan->application && application_uses_internal_funding($loan->application)))
        <div class="mt-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
            This product uses capital partner funding. Capital will be allocated automatically when this loan is disbursed.
        </div>
    @endif

    {{-- Fees applied at disbursement --}}
    <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700">Fees &amp; Charges</h3>
            <div class="text-sm text-gray-600">
                Net disbursed:
                <span class="font-semibold text-gray-900">{{ format_money((float) ($loan->net_disbursed_amount ?? max(0, (float)$loan->approved_amount - (float)$loan->fees_total)) ) }}</span>
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
                                <td class="py-2 pr-4 text-right">{{ $fee->basis === 'percentage' ? rtrim(rtrim(format_number((float) $fee->rate_or_amount, 4), '0'), '.').'%' : format_number((float) $fee->rate_or_amount) }}</td>
                                <td class="py-2 pr-4 text-right font-semibold">{{ format_number((float) $fee->computed_amount) }}</td>
                                <td class="py-2 pr-4 capitalize text-xs">{{ $fee->status }}</td>
                                <td class="py-2 text-xs text-gray-500">{{ optional($fee->charged_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200">
                            <td colspan="4" class="py-2 pr-4 text-right text-xs uppercase text-gray-500">Total fees</td>
                            <td class="py-2 pr-4 text-right font-bold">{{ format_number((float) $loan->fees_total) }}</td>
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
                {{ $schedule->count() }} installments · Paid {{ format_money($sumPaid) }} / {{ format_number($sumTotal) }}
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
</x-admin.layout>