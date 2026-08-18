@php
    $customer = $loan->customer;
    $product = $loan->product;
    $writeOffService = app(\App\Services\WriteOffRequestService::class);
    $canRecommend = $writeOffService->canRecommend(auth()->user())
        && $writeOffService->loanEligibleForRecommendation($loan)
        && ! $writeOffService->hasOpenRequest($loan);
@endphp

<x-admin.layout
    title="Write off {{ $loan->loan_number }}"
    heading=""
    :backUrl="route('admin.loans.show', $loan)"
    backLabel="Back to loan file">

    <div class="mb-5 -mt-2 rounded-2xl overflow-hidden ring-1 ring-brand/20 shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-6 py-5 text-white">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="flex items-start gap-4 min-w-0">
                    <div class="shrink-0 rounded-xl bg-white/10 ring-1 ring-white/20 p-2.5">
                        <x-site.brand-mark size="sm" variant="light" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ brand_name() }} · Write-off</p>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 truncate">{{ $loan->loan_number }}</h1>
                        <p class="text-sm text-white/75 mt-1 truncate">
                            {{ $customer?->full_name ?: trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: '—' }}
                            @if ($product)
                                <span class="text-white/50">·</span> {{ $product->name }}
                            @endif
                        </p>
                        <p class="text-xs text-white/70 mt-1.5 flex flex-wrap gap-x-3 gap-y-1">
                            <span>Outstanding {{ format_money((float) $loan->outstanding_balance) }}</span>
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
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-2xl bg-red-50 ring-1 ring-red-200 px-5 py-4 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if (! empty($approvalRequired))
        <div class="mb-5 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-900">
            Write-off approval is required. Recommend below — manager and finance must approve before execution.
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 p-6 space-y-4 max-w-xl">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Write-off request</p>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-[10px] uppercase tracking-widest text-gray-500">Customer</div>
                <div class="font-medium mt-1">{{ optional($loan->customer)->first_name }} {{ optional($loan->customer)->last_name }}</div>
            </div>
            <div>
                <div class="text-[10px] uppercase tracking-widest text-gray-500">Outstanding balance</div>
                <div class="font-medium mt-1">{{ format_money((float) $loan->outstanding_balance) }}</div>
            </div>
            <div>
                <div class="text-[10px] uppercase tracking-widest text-gray-500">Status</div>
                <div class="font-medium mt-1">{{ display_label($loan->status, 'loan_status') }}</div>
            </div>
            <div>
                <div class="text-[10px] uppercase tracking-widest text-gray-500">Disbursed</div>
                <div class="font-medium mt-1">{{ optional($loan->disbursement_date)->toDateString() ?? '—' }}</div>
            </div>
        </div>

        @if (! empty($approvalRequired) && $canRecommend)
            <form method="POST" action="{{ route('admin.loans.write-off-requests.store', $loan) }}" class="space-y-4 pt-4 border-t border-gray-100"
                  @submit.prevent="window.confirmForm($el, {
                      title: @js('Recommend write-off for '.$loan->loan_number.'?'),
                      message: @js('This does not write the loan off. It sends a request to the write-off queue. A manager must approve, then finance executes it to the General Ledger.'),
                      confirmLabel: @js('Send recommendation'),
                      confirmClass: 'bg-rose-600 hover:bg-rose-500 text-white',
                      tone: 'warning',
                  })">
                @csrf
                <div>
                    <x-admin.money-input name="amount" label="Amount to write off (TZS)" :value="old('amount', (float) $loan->outstanding_balance)" :decimals="2" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea name="reason" rows="3" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="bg-rose-600 hover:bg-rose-500 text-white font-semibold px-4 py-2 rounded-lg text-sm">Recommend write-off</button>
                    <a href="{{ route('admin.write-off-requests.index') }}" class="text-sm text-brand hover:underline">View queue</a>
                </div>
            </form>
        @elseif (! empty($approvalRequired))
            <p class="text-sm text-gray-600 pt-4 border-t border-gray-100">
                This loan already has a pending write-off request or you are not authorized to recommend write-offs.
                <a href="{{ route('admin.write-off-requests.index') }}" class="text-amber-700 font-semibold hover:underline">View write-off queue</a>
            </p>
        @else
            <form method="POST" action="{{ route('admin.loans.write-off', $loan) }}" class="space-y-4 pt-4 border-t border-gray-100"
                  @submit.prevent="window.confirmForm($el, {
                      title: @js('Write off '.$loan->loan_number.'?'),
                      message: @js('Write off '.$loan->loan_number.'? This posts to the General Ledger and marks the loan written_off.'),
                      confirmLabel: @js('Write off loan'),
                      confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                      tone: 'warning',
                  })">
                @csrf
                <div>
                    <x-admin.money-input name="amount" label="Amount to write off (TZS)" :value="old('amount', (float) $loan->outstanding_balance)" :decimals="2" required help="Defaults to the full outstanding balance." />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea name="reason" rows="3" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="bg-rose-600 hover:bg-rose-500 text-white font-semibold px-4 py-2 rounded-lg text-sm">Write off loan</button>
                    <a href="{{ route('admin.loans.show', $loan) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                </div>
            </form>
        @endif
    </div>
</x-admin.layout>
