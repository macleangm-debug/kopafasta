<div class="mb-6">
    <h2 class="text-lg font-semibold mb-1">{{ __('borrower.loans_page.tab_active') }}</h2>
    <p class="text-sm text-gray-500">{{ __('borrower.loans_page.active_hint') }}</p>
</div>

@if ($loans->isEmpty())
    <x-site.empty-state
        icon="📄"
        :title="__('borrower.loans_page.empty_title')"
        :description="__('borrower.loans_page.empty_desc')"
        :action-label="__('borrower.loans_page.empty_action')"
        :action-url="route('site.borrower.apply')"
    />
@else
    <div class="space-y-4">
        @foreach ($loans as $loan)
            @php
                $paid = max(0, $loan->principal_amount - $loan->outstanding_balance);
                $pct = $loan->principal_amount > 0 ? min(100, ($paid / $loan->principal_amount) * 100) : 0;
                $statusBadge = match ($loan->status) {
                    'active','disbursed' => 'bg-emerald-100 text-emerald-700',
                    'arrears'            => 'bg-red-100 text-red-700',
                    'closed'             => 'bg-gray-100 text-gray-700',
                    default              => 'bg-amber-100 text-amber-700',
                };
                $monthly = $loan->tenure_months > 0 ? round(($loan->principal_amount / $loan->tenure_months) + ($loan->principal_amount * $loan->interest_rate)) : 0;
            @endphp
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                    <div>
                        <p class="text-xs text-gray-500">{{ $loan->product->name ?? '—' }}</p>
                        <p class="font-mono font-bold text-lg">{{ $loan->loan_number }}</p>
                    </div>
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusBadge }}">{{ ucfirst($loan->status) }}</span>
                </div>

                <div class="grid sm:grid-cols-4 gap-4 mb-5">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_amount') }}</p>
                        <p class="font-semibold text-sm">{{ format_money($loan->principal_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.outstanding') }}</p>
                        <p class="font-semibold text-sm">{{ format_money($loan->outstanding_balance) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.monthly') }}</p>
                        <p class="font-semibold text-sm">{{ format_money($monthly) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.rate_tenure_label') }}</p>
                        <p class="font-semibold text-sm">{{ __('borrower.loans_page.rate_tenure', ['rate' => number_format($loan->interest_rate * 100, 2), 'months' => $loan->tenure_months]) }}</p>
                    </div>
                </div>

                <div class="mb-5">
                    <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                        <span>{{ __('borrower.loans_page.repaid_pct', ['pct' => number_format($pct, 0)]) }}</span>
                        <span>{{ __('borrower.loans_page.matures', ['date' => $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('d M Y') : '—']) }}</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('site.borrower.schedule', $loan->id) }}" class="bg-gray-900 hover:bg-gray-800 text-white text-xs font-semibold px-4 py-2 rounded-full">{{ __('borrower.loans_page.view_schedule') }}</a>
                    <a href="{{ route('site.borrower.payments') }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 text-xs font-semibold px-4 py-2 rounded-full">{{ __('borrower.loans_page.make_payment') }}</a>
                </div>
            </div>
        @endforeach
    </div>
@endif
