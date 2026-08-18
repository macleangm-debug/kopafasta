@php
    $customer = $loan->customer;
    $product = $loan->product;
    $application = $loan->application;
    $writeOffService = app(\App\Services\WriteOffRequestService::class);
    $writeOffApprovalRequired = (bool) \App\Models\Setting::get('finance.write_off_approval_required');
    $canRecommendWriteOff = $writeOffApprovalRequired
        && $writeOffService->canRecommend(auth()->user())
        && $writeOffService->loanEligibleForRecommendation($loan)
        && ! $writeOffService->hasOpenRequest($loan);
    $canSeeWriteOffQueue = $writeOffService->canSeeWriteOffActions(auth()->user());
    $canDirectWriteOff = ! $writeOffApprovalRequired
        && $writeOffService->canFinanceApprove(auth()->user())
        && $writeOffService->loanEligibleForRecommendation($loan);
@endphp

<x-admin.layout
    title="Loan {{ $loan->loan_number }}"
    heading=""
    :backUrl="route('admin.loans.index')"
    backLabel="Back to loans">

    <div class="mb-5 -mt-2 rounded-2xl overflow-hidden ring-1 ring-brand/20 shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-6 py-5 text-white">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="flex items-start gap-4 min-w-0">
                    <div class="shrink-0 rounded-xl bg-white/10 ring-1 ring-white/20 p-2.5">
                        <x-site.brand-mark size="sm" variant="light" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ brand_name() }} · Loan file</p>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 truncate">{{ $loan->loan_number }}</h1>
                        <p class="text-sm text-white/75 mt-1 truncate">
                            {{ $customer?->full_name ?: trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: '—' }}
                            @if ($customer?->member_no)
                                <span class="text-white/50">·</span> Member {{ $customer->member_no }}
                            @endif
                            @if ($product)
                                <span class="text-white/50">·</span> {{ $product->name }}
                            @endif
                        </p>
                        <p class="text-xs text-white/70 mt-1.5 flex flex-wrap gap-x-3 gap-y-1">
                            @if ($customer?->phone)
                                <span>{{ $customer->phone }}</span>
                            @endif
                            @if ($application)
                                <span>App {{ $application->application_number }}</span>
                            @endif
                            <span>{{ format_money((float) ($loan->approved_amount ?? $loan->principal_amount)) }}</span>
                            <span>{{ $loan->tenure_months }} months</span>
                            @if ($loan->disbursement_date)
                                <span>Disbursed {{ $loan->disbursement_date->format('d M Y') }}</span>
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white ring-1 ring-white/20">
                        {{ display_label($loan->status, 'loan_status') }}
                    </span>
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
                                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">
                                    Disburse
                                </button>
                            </form>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white/80 ring-1 ring-white/15"
                                  title="{{ implode(' ', $disbursementBlocking ?? []) }}">
                                Disburse locked
                            </span>
                        @endif
                    @endif
                    @if ($canRecommendWriteOff)
                        <a href="{{ route('admin.loans.write-off-form', $loan) }}"
                           class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-500 hover:bg-rose-400 text-white">
                            Recommend write-off
                        </a>
                    @endif
                    @if ($canDirectWriteOff)
                        <a href="{{ route('admin.loans.write-off-form', $loan) }}"
                           class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-500 hover:bg-rose-400 text-white">
                            Write off
                        </a>
                    @endif
                    @if ($canSeeWriteOffQueue && in_array($loan->status, ['arrears', 'defaulted', 'written_off'], true))
                        <a href="{{ route('admin.write-off-requests.index') }}"
                           class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-white/10 hover:bg-white/20 text-white ring-1 ring-white/20">
                            Write-off queue
                        </a>
                    @endif
                    @if (! $loan->isServicingLocked())
                        <a href="{{ route('admin.loans.edit', $loan) }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">
                            Edit
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

@if (! empty($disbursementBlocking) && $loan->status === 'pending')
    <div class="mb-5 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4">
        <p class="text-xs uppercase tracking-widest text-amber-800 font-bold">Disbursement blocked</p>
        <ul class="mt-2 space-y-1 text-sm text-amber-950">
            @foreach ($disbursementBlocking as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
        @if ($application)
            <a href="{{ route('admin.loan-applications.show', $application) }}"
               class="mt-3 inline-flex text-xs font-semibold text-amber-950 hover:underline">Open credit file →</a>
        @endif
    </div>
@endif

@if (! empty($disbursementChecklist) && $loan->status === 'pending')
    <div class="mb-5 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-brand-muted/60 to-white px-5 py-3 border-b border-brand/10">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Disbursement prerequisites</p>
            <p class="text-sm font-semibold text-gray-900 mt-0.5">
                {{ $application?->application_number ?? $loan->loan_number }}
            </p>
        </div>
        <div class="px-5 py-4 grid lg:grid-cols-2 gap-4">
            <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Approved amount</dt>
                    <dd class="font-bold text-gray-900 mt-1">{{ format_money((float) $loan->approved_amount) }}</dd>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Post-approval fee</dt>
                    <dd class="font-bold mt-1 {{ ($disbursementChecklist['post_approval_fee']['complete'] ?? false) ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ ($disbursementChecklist['post_approval_fee']['complete'] ?? false) ? 'Paid' : 'Pending' }}
                    </dd>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">Contract</dt>
                    <dd class="font-bold mt-1 {{ ($disbursementChecklist['contract']['complete'] ?? false) ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ ($disbursementChecklist['contract']['complete'] ?? false) ? 'Accepted' : 'Pending' }}
                    </dd>
                </div>
            </dl>
            @if (! empty($disbursementDestination['method'] ?? null))
                <div class="rounded-2xl bg-brand-muted/40 ring-1 ring-brand/10 p-4 text-sm">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">Payout destination</p>
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
        <ul class="divide-y divide-gray-100 border-t border-gray-100">
            @foreach ($disbursementChecklist as $item)
                @php
                    $statusText = match ($item['status']) {
                        'paid' => 'Paid',
                        'accepted' => 'Accepted',
                        'complete' => 'Complete',
                        'not_required' => 'Not required',
                        'available' => 'Available',
                        'insufficient' => 'Insufficient',
                        'pending' => 'Pending',
                        'locked' => 'Locked',
                        'not_generated' => 'Not generated',
                        default => ucfirst($item['status']),
                    };
                    $tone = ($item['complete'] ?? false) ? 'text-emerald-700' : (($item['status'] ?? '') === 'locked' ? 'text-gray-500' : 'text-amber-700');
                @endphp
                <li class="px-5 py-3 flex items-center justify-between text-sm">
                    <span class="font-medium text-gray-900">{{ $item['label'] }}</span>
                    <span class="font-semibold {{ $tone }}">{{ $statusText }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

    @if ($loan->status === 'active' && ($canReverseDisbursement ?? false))
        <details class="mb-5 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm">
            <summary class="cursor-pointer list-none px-5 py-3 text-xs font-semibold text-orange-800 flex items-center justify-between">
                Reverse disbursement
                <span class="text-gray-400 font-normal">Ops only · rolls capital and schedule back</span>
            </summary>
            <form method="POST" action="{{ route('admin.loans.reverse-disbursement', $loan) }}"
                  class="px-5 pb-4 border-t border-gray-100"
                  @submit.prevent="window.confirmForm($el, {
                      title: @js(__('admin.confirm.loan_reverse_disbursement_title')),
                      message: @js(__('admin.confirm.loan_reverse_disbursement_message')),
                      confirmLabel: @js(__('admin.confirm.loan_reverse_disbursement_confirm')),
                      confirmClass: 'bg-orange-600 hover:bg-orange-700 text-white',
                      tone: 'warning',
                  })">
                @csrf
                <p class="text-sm text-gray-600 mt-3 mb-3">Capital allocation and schedules will be rolled back. The application returns to the disbursement queue.</p>
                <label for="reverse-reason" class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1">Reason</label>
                <textarea id="reverse-reason" name="reason" rows="3" required maxlength="500"
                          class="w-full rounded-lg border-gray-300 text-sm mb-3"
                          placeholder="e.g. Disbursed in error — borrower not ready"></textarea>
                <button type="submit"
                        class="inline-flex items-center text-xs font-semibold text-white bg-orange-600 hover:bg-orange-700 px-3 py-2 rounded-lg">
                    Confirm reversal
                </button>
            </form>
        </details>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Summary card --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 p-6">
            <div class="flex items-start justify-between mb-4 gap-4">
                <div class="max-w-md flex-1">
                    <x-loan-balance-breakdown
                        :breakdown="$servicing['balance_breakdown'] ?? app(\App\Services\LoanBalanceService::class)->breakdown($loan)"
                        :recovery-charges="app(\App\Services\RecoveryChargesService::class)->breakdownForLoan($loan)"
                        :expanded="true"
                    />
                </div>
                <span @class([
                    'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider',
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
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 p-6">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">File record</p>
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
        @include('admin.loans._payment_request', ['loan' => $loan, 'suggestedPay' => (float) $loan->outstanding_balance])
        @include('admin.loans._servicing')
    @endif

    @include('admin.loans._manual-capital-allocation')

    @if ($loan->capitalAllocations->isNotEmpty() && ! in_array($loan->status, ['pending'], true))
        <div class="mt-4 bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 p-6">
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
        <div class="mt-4 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-900">
            This product uses capital partner funding. Capital will be allocated automatically when this loan is disbursed.
        </div>
    @endif

    {{-- Fees applied at disbursement --}}
    <div class="mt-4 bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 p-6">
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
    <div class="mt-4 bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 p-6">
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