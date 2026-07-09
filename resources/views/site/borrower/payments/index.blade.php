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

    @if ($loans->isNotEmpty())
        <div class="mb-6 rounded-2xl bg-gradient-to-br from-brand to-brand-light text-white p-5 sm:p-6 relative overflow-hidden ring-1 ring-brand/20">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
            <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">{{ __('borrower.payments_page.make_repayment') }}</p>
                    <p class="mt-1 text-lg font-bold">{{ __('borrower.payments_page.subtitle') }}</p>
                </div>
                <a href="{{ route('site.borrower.payments.create', ['loan' => $loans->first()->id]) }}"
                   class="inline-flex justify-center bg-white text-brand hover:bg-white/90 font-semibold px-5 py-2.5 rounded-xl text-sm shadow-sm">
                    {{ __('borrower.payments_page.make_repayment') }}
                </a>
            </div>
        </div>
    @endif

    @if ($entries->isNotEmpty() || $loans->isNotEmpty())
        <div class="grid gap-4 sm:grid-cols-3 mb-6">
            <div class="glass-card p-5 bg-gradient-to-br from-brand-muted/70 to-white ring-1 ring-brand/10">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.summary_verified') }}</p>
                <p class="mt-2 text-2xl font-black text-brand tabular-nums">{{ format_money($verifiedTotal) }}</p>
            </div>
            <div class="glass-card p-5 ring-1 ring-brand/10">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.summary_pending') }}</p>
                <p class="mt-2 text-2xl font-black text-amber-600 tabular-nums">{{ $pendingCount }}</p>
            </div>
            <div class="glass-card p-5 ring-1 ring-brand/10">
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
        <div class="glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="sm:hidden divide-y divide-gray-100">
                @foreach ($entries as $entry)
                    @php
                        $badge = match ($entry['status']) {
                            'verified', 'paid' => 'bg-emerald-50 text-emerald-700',
                            'rejected', 'cancelled' => 'bg-red-50 text-red-700',
                            'clarification_requested', 'awaiting_payout' => 'bg-sky-50 text-sky-700',
                            default => 'bg-amber-50 text-amber-800',
                        };
                    @endphp
                    <a href="{{ $entry['url'] }}" class="block px-4 py-4 hover:bg-brand-muted/20 transition">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-semibold text-brand truncate">{{ $entry['reference'] }}</p>
                                <p class="text-sm text-gray-700 mt-1">{{ $entry['type_label'] }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ $entry['date']?->format('d M Y') }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-semibold tabular-nums text-gray-900">{{ format_money($entry['amount']) }}</p>
                                <span class="inline-flex mt-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">{{ $entry['status_label'] }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="hidden sm:block overflow-x-auto">
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
