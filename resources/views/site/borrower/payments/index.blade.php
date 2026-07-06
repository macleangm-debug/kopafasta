<x-site.borrower-layout :title="brand_title(__('borrower.payments_page.title'))" active="payments" content-width="wide">

    <x-site.borrower-page-header
        :eyebrow="__('borrower.nav.payments')"
        :title="__('borrower.payments_page.title')"
        :subtitle="__('borrower.payments_page.subtitle')">
        <x-slot:actions>
            @if ($loans->isNotEmpty())
                <a href="{{ route('site.borrower.payments.create') }}"
                   class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm shadow-sm">
                    {{ __('borrower.payments_page.make_repayment') }}
                </a>
            @endif
        </x-slot:actions>
    </x-site.borrower-page-header>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @php
        $verifiedTotal = $entries->whereIn('status', ['verified', 'paid'])->sum('amount');
        $pendingCount = $entries->whereIn('status', ['pending', 'submitted', 'awaiting_payout'])->count();
        $activeLoanCount = $loans->count();
    @endphp

    @if ($entries->isNotEmpty() || $loans->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-3 mb-6">
            <div class="glass-card p-5 bg-gradient-to-br from-brand-muted/70 to-white">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.summary_verified') }}</p>
                <p class="mt-2 text-2xl font-black text-brand tabular-nums">{{ format_money($verifiedTotal) }}</p>
            </div>
            <div class="glass-card p-5">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.summary_pending') }}</p>
                <p class="mt-2 text-2xl font-black text-amber-600 tabular-nums">{{ $pendingCount }}</p>
            </div>
            <div class="glass-card p-5">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.summary_active_loans') }}</p>
                <p class="mt-2 text-2xl font-black text-gray-900 tabular-nums">{{ $activeLoanCount }}</p>
            </div>
        </div>
    @endif

    <x-site.page-loading-shell>
        <x-slot:skeleton>
            <x-site.skeleton-card :lines="6" class="mb-4" />
            <x-site.skeleton-card :lines="4" />
        </x-slot:skeleton>

    @if ($entries->isEmpty())
        <x-site.empty-state
            icon="💳"
            :title="__('borrower.payments_page.empty_title')"
            :description="$loans->isEmpty()
                ? __('borrower.payments_page.empty_desc_no_loans')
                : __('borrower.payments_page.empty_desc_has_loans')"
            :action-label="$loans->isNotEmpty() ? __('borrower.payments_page.empty_action_repayment') : __('borrower.payments_page.empty_action_products')"
            :action-url="$loans->isNotEmpty() ? route('site.borrower.payments.create') : route('site.borrower.dashboard')"
        />
    @else
        <div class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50/80 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('borrower.payments_page.col_date') }}</th>
                            <th class="px-4 py-3">{{ __('borrower.payments_page.col_reference') }}</th>
                            <th class="px-4 py-3">{{ __('borrower.payments_page.col_type') }}</th>
                            <th class="px-4 py-3">{{ __('borrower.payments_page.col_amount') }}</th>
                            <th class="px-4 py-3">{{ __('borrower.payments_page.col_status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($entries as $entry)
                            <tr class="hover:bg-brand-muted/20 cursor-pointer transition" onclick="window.location='{{ $entry['url'] }}'">
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $entry['date']?->format('d-M-Y') }}</td>
                                <td class="px-4 py-3 font-mono text-xs font-semibold text-brand">{{ $entry['reference'] }}</td>
                                <td class="px-4 py-3">{{ $entry['type_label'] }}</td>
                                <td class="px-4 py-3 font-medium tabular-nums">{{ format_money($entry['amount']) }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $badge = match ($entry['status']) {
                                            'verified', 'paid' => 'bg-emerald-50 text-emerald-700',
                                            'rejected', 'cancelled' => 'bg-red-50 text-red-700',
                                            'clarification_requested', 'awaiting_payout' => 'bg-sky-50 text-sky-700',
                                            default => 'bg-amber-50 text-amber-800',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">
                                        {{ $entry['status_label'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    </x-site.page-loading-shell>

</x-site.borrower-layout>
