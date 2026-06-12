<x-site.borrower-layout
    :title="brand_title($loan->loan_number)"
    active="loans">

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans', ['tab' => 'active']) }}" class="text-xs text-gray-500 hover:text-gray-700">
            {{ __('borrower.loans_page.back') }}
        </a>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ $servicing['product_name'] ?? '—' }}</p>
            <h1 class="text-2xl font-bold font-mono">{{ $servicing['loan_reference'] }}</h1>
            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.loans_page.active_loan') }}</p>
        </div>
        @php
            $statusBadge = match ($loan->status) {
                'active', 'disbursed' => 'bg-emerald-100 text-emerald-700',
                'arrears' => 'bg-red-100 text-red-700',
                'closed' => 'bg-gray-100 text-gray-700',
                default => 'bg-amber-100 text-amber-700',
            };
        @endphp
        <span class="text-xs font-semibold rounded-full px-3 py-1.5 {{ $statusBadge }}">{{ $servicing['status_label'] ?? ucfirst($loan->status) }}</span>
    </div>

    @if ($servicing['in_arrears'])
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">{{ __('borrower.loans_page.arrears_alert') }}</p>
            @if (($servicing['amount_in_arrears'] ?? 0) > 0)
                <p class="mt-1">{{ __('borrower.loans_page.arrears_amount', ['amount' => format_money($servicing['amount_in_arrears']), 'count' => $servicing['overdue_installments']]) }}</p>
            @endif
        </div>
    @endif

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.outstanding') }}</p>
            <p class="text-lg font-bold mt-1">{{ format_money($servicing['outstanding_balance']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_amount') }}</p>
            <p class="text-lg font-bold mt-1">{{ format_money($servicing['principal']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.next_due') }}</p>
            <p class="text-lg font-bold mt-1">
                @if ($servicing['next_due_date'])
                    {{ $servicing['next_due_date']->format('d M Y') }}
                @else
                    —
                @endif
            </p>
            @if ($servicing['next_due_amount'])
                <p class="text-xs text-gray-500 mt-0.5">{{ format_money($servicing['next_due_amount']) }}</p>
            @endif
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.days_remaining') }}</p>
            <p class="text-lg font-bold mt-1 {{ ($servicing['days_remaining'] ?? 0) < 0 ? 'text-red-700' : '' }}">
                @if ($servicing['days_remaining'] !== null)
                    {{ $servicing['days_remaining'] < 0
                        ? __('borrower.loans_page.days_overdue', ['days' => abs($servicing['days_remaining'])])
                        : __('borrower.loans_page.days_left', ['days' => $servicing['days_remaining']]) }}
                @else
                    —
                @endif
            </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.maturity') }}</p>
            <p class="text-lg font-bold mt-1">
                {{ $servicing['maturity_date'] ? \Illuminate\Support\Carbon::parse($servicing['maturity_date'])->format('d M Y') : '—' }}
            </p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.repaid_pct', ['pct' => format_number($servicing['progress_pct'], 0)]) }}</p>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden mt-3">
                <div class="h-full {{ $servicing['in_arrears'] ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ $servicing['progress_pct'] }}%"></div>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('site.borrower.schedule', $loan->id) }}" class="bg-gray-900 hover:bg-gray-800 text-white text-xs font-semibold px-4 py-2 rounded-full">{{ __('borrower.loans_page.view_schedule') }}</a>
        <a href="{{ route('site.borrower.payments.create', ['loan' => $loan->id]) }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 text-xs font-semibold px-4 py-2 rounded-full">{{ __('borrower.loans_page.make_payment') }}</a>
    </div>

    @if ($recentRepayments->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <h2 class="font-semibold mb-4">{{ __('borrower.loans_page.recent_payments') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($recentRepayments as $payment)
                    <li class="py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ $payment->reference ?? 'Payment' }}</p>
                            <p class="text-xs text-gray-500">{{ optional($payment->paid_at)->format('d M Y, H:i') ?? '—' }}</p>
                        </div>
                        <span class="font-semibold">{{ format_money($payment->amount) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-site.borrower-layout>
