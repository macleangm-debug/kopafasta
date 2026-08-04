<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-lg font-bold text-gray-900 mb-1">{{ __('borrower.loans_page.tab_active') }}</h2>
        <p class="text-sm text-gray-500">{{ __('borrower.loans_page.active_hint') }}</p>
    </div>
    <div class="inline-flex rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-0.5 text-xs">
        <a href="{{ route('site.borrower.loans', ['tab' => 'active', 'view' => 'cards']) }}"
           class="px-3 py-1.5 rounded-lg font-semibold {{ ($viewMode ?? 'cards') === 'cards' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.cards') }}
        </a>
        <a href="{{ route('site.borrower.loans', ['tab' => 'active', 'view' => 'table']) }}"
           class="px-3 py-1.5 rounded-lg font-semibold {{ ($viewMode ?? 'cards') === 'table' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.table') }}
        </a>
    </div>
</div>

@if ($loans->isEmpty())
    <x-site.empty-state
        icon="📄"
        :title="__('borrower.loans_page.empty_title')"
        :description="__('borrower.loans_page.empty_desc')"
        :action-label="__('borrower.loans_page.empty_action')"
        :action-url="route('site.borrower.loan-products')"
    />
@else
    @if (($viewMode ?? 'cards') === 'table')
        <div class="glass-card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/80 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Loan</th>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3 text-right">Outstanding</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($loans as $loan)
                        <tr class="hover:bg-brand-muted/20">
                            <td class="px-4 py-3 font-mono font-semibold">{{ $loan->loan_number }}</td>
                            <td class="px-4 py-3">{{ $loan->product->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums">{{ format_money($loan->outstanding_balance) }}</td>
                            <td class="px-4 py-3 capitalize">{{ $loan->status }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('site.borrower.loans.show', $loan) }}" class="text-brand font-semibold hover:underline">{{ __('borrower.loans_page.view_loan') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
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
                    default              => 'bg-brand-muted text-brand',
                };
                $monthly = $loan->tenure_months > 0 ? round(($loan->principal_amount / $loan->tenure_months) + ($loan->principal_amount * $loan->interest_rate)) : 0;
            @endphp
            <div class="glass-card p-6">
                <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                    <div>
                        <p class="text-xs text-gray-500">{{ $loan->product->name ?? '—' }}</p>
                        <p class="font-mono font-bold text-lg text-brand">{{ $loan->loan_number }}</p>
                    </div>
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusBadge }}">{{ ucfirst($loan->status) }}</span>
                </div>

                <div class="grid sm:grid-cols-4 gap-4 mb-5">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.loan_amount') }}</p>
                        <p class="font-semibold text-sm tabular-nums">{{ format_money($loan->principal_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.outstanding') }}</p>
                        <p class="font-semibold text-sm tabular-nums">{{ format_money($loan->outstanding_balance) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.monthly') }}</p>
                        <p class="font-semibold text-sm tabular-nums">{{ format_money($monthly) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.rate_tenure_label') }}</p>
                        <p class="font-semibold text-sm">{{ __('borrower.loans_page.rate_tenure', ['rate' => format_number($loan->interest_rate * 100, 2), 'months' => $loan->tenure_months]) }}</p>
                    </div>
                </div>

                <div class="mb-5">
                    <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                        <span>{{ __('borrower.loans_page.repaid_pct', ['pct' => format_number($pct, 0)]) }}</span>
                        <span>{{ __('borrower.loans_page.matures', ['date' => $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('d M Y') : '—']) }}</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-brand" style="width: {{ $pct }}%"></div>
                    </div>
                </div>

                @php
                    $policy = app(\App\Services\LoanPolicyService::class);
                    $canRestructure = $policy->canRestructureLoan($loan) === null;
                    $canTopUp = $policy->canRequestTopUp($loan) === null;
                @endphp
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('site.borrower.loans.show', $loan) }}" class="bg-white ring-1 ring-gray-200/80 hover:bg-brand-muted/30 text-gray-800 text-xs font-semibold px-4 py-2 rounded-xl">{{ __('borrower.loans_page.view_loan') }}</a>
                    <a href="{{ route('site.borrower.schedule', $loan->id) }}" class="bg-brand hover:bg-brand-light text-white text-xs font-semibold px-4 py-2 rounded-xl">{{ __('borrower.loans_page.view_schedule') }}</a>
                    <a href="{{ route('site.borrower.payments') }}" class="bg-brand-gold hover:bg-yellow-400 text-brand text-xs font-bold px-4 py-2 rounded-xl">{{ __('borrower.loans_page.make_payment') }}</a>
                    @if ($canRestructure)
                        <a href="{{ route('site.borrower.loans.restructure', $loan) }}" class="bg-white ring-1 ring-gray-200/80 hover:bg-brand-muted/30 text-gray-800 text-xs font-semibold px-4 py-2 rounded-xl">{{ __('borrower.loan_actions.restructure') }}</a>
                    @endif
                    @if ($canTopUp)
                        <a href="{{ route('site.borrower.loans.top-up', $loan) }}" class="bg-white ring-1 ring-gray-200/80 hover:bg-brand-muted/30 text-gray-800 text-xs font-semibold px-4 py-2 rounded-xl">{{ __('borrower.loan_actions.top_up') }}</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif
@endif
