<x-site.borrower-layout :title="brand_title(__('borrower.payments_page.title'))" active="payments" content-width="wide">

    @if (session('status'))
        <div class="mb-5 rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @php
        $verifiedTotal = $entries->whereIn('status', ['verified', 'paid'])->sum('amount');
        $pendingCount = $entries->whereIn('status', ['pending', 'submitted', 'awaiting_payout'])->count();
        $hasLoans = $loans->isNotEmpty();
        $focus = $focusLoan ?? null;
        $focusLoanModel = $focus['loan'] ?? null;
        $dueAmount = $focus['next_due_amount'] ?? null;
        $dueDate = $focus['next_due_date'] ?? null;
        $inArrears = (bool) ($focus['in_arrears'] ?? false);
        $payUrl = $focusLoanModel
            ? route('site.borrower.payments.create', ['loan' => $focusLoanModel->id])
            : route('site.borrower.payments.create');
    @endphp

    {{-- Hero: next payment / empty state --}}
    @if ($hasLoans && $focus)
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand via-brand to-brand/90 text-white shadow-lg shadow-brand/20 mb-8">
            <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
            <div class="relative px-5 sm:px-8 py-7 sm:py-9">
                <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ __('borrower.nav.payments') }}</p>
                <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight">
                    {{ $inArrears ? __('borrower.payments_page.hero_arrears_title') : __('borrower.payments_page.hero_due_title') }}
                </h1>
                <p class="mt-2 text-sm text-white/75 max-w-xl">
                    {{ $focus['product_name'] ?? $focus['loan_reference'] }}
                    @if ($dueDate)
                        · {{ $inArrears
                            ? __('borrower.payments_page.hero_overdue_since', ['date' => $dueDate->format('d M Y')])
                            : __('borrower.payments_page.hero_due_on', ['date' => $dueDate->format('d M Y')]) }}
                    @endif
                </p>

                <div class="mt-6 grid grid-cols-2 sm:grid-cols-3 gap-4 sm:gap-6">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/60">
                            {{ $inArrears ? __('borrower.payments_page.hero_arrears_amount') : __('borrower.payments_page.hero_due_amount') }}
                        </p>
                        <p class="mt-1 text-2xl sm:text-3xl font-extrabold tabular-nums tracking-tight text-amber-300">
                            {{ format_money((float) ($inArrears ? ($focus['amount_in_arrears'] ?: $dueAmount) : $dueAmount)) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('borrower.payments_page.hero_outstanding') }}</p>
                        <p class="mt-1 text-xl sm:text-2xl font-extrabold tabular-nums tracking-tight">
                            {{ format_money((float) ($focus['outstanding_balance'] ?? 0)) }}
                        </p>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('borrower.payments_page.hero_progress') }}</p>
                        <p class="mt-1 text-xl sm:text-2xl font-extrabold tracking-tight">
                            {{ (int) ($focus['progress_pct'] ?? 0) }}%
                        </p>
                        <div class="mt-2 h-1.5 rounded-full bg-white/20 overflow-hidden">
                            <div class="h-full rounded-full bg-amber-300" style="width: {{ min(100, (float) ($focus['progress_pct'] ?? 0)) }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ $payUrl }}"
                       class="inline-flex items-center justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-full text-sm shadow-sm transition">
                        {{ __('borrower.payments_page.make_repayment') }}
                    </a>
                </div>
            </div>
        </section>
    @else
        <x-site.borrower-page-header
            :eyebrow="__('borrower.nav.payments')"
            :title="__('borrower.payments_page.title')"
            :subtitle="__('borrower.payments_page.subtitle')"
        />
    @endif

    <x-site.page-loading-shell>
        <x-slot:skeleton>
            <x-site.skeleton-card :lines="6" class="mb-4" />
            <x-site.skeleton-card :lines="4" />
        </x-slot:skeleton>

        @if ($entries->isNotEmpty() || $hasLoans)
            <div class="mb-8 grid gap-px overflow-hidden rounded-2xl bg-brand/10 ring-1 ring-brand/10 sm:grid-cols-3">
                <div class="bg-gradient-to-b from-brand-muted/40 to-white px-5 py-5">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.summary_verified') }}</p>
                    <p class="mt-2 text-2xl font-black text-brand tabular-nums">{{ format_money($verifiedTotal) }}</p>
                </div>
                <div class="bg-white px-5 py-5">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.summary_pending') }}</p>
                    <p class="mt-2 text-2xl font-black text-amber-600 tabular-nums">{{ $pendingCount }}</p>
                </div>
                <div class="bg-white px-5 py-5">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.payments_page.summary_active_loans') }}</p>
                    <p class="mt-2 text-2xl font-black text-gray-900 tabular-nums">{{ $loans->count() }}</p>
                </div>
            </div>
        @endif

        <section>
            <div class="mb-4 flex items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 tracking-tight">{{ __('borrower.payments_page.history_title') }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">{{ __('borrower.payments_page.history_subtitle') }}</p>
                </div>
                @if ($hasLoans && ! $focus)
                    <a href="{{ route('site.borrower.payments.create') }}"
                       class="hidden sm:inline-flex text-sm font-semibold text-brand hover:underline">
                        {{ __('borrower.payments_page.make_repayment') }}
                    </a>
                @endif
            </div>

            @if ($entries->isEmpty())
                <x-site.empty-state
                    icon="◎"
                    :title="__('borrower.payments_page.empty_title')"
                    :description="$hasLoans
                        ? __('borrower.payments_page.empty_desc_has_loans')
                        : __('borrower.payments_page.empty_desc_no_loans')"
                    :action-label="$hasLoans ? __('borrower.payments_page.empty_action_repayment') : __('borrower.payments_page.empty_action_products')"
                    :action-url="$hasLoans ? route('site.borrower.payments.create') : route('site.borrower.dashboard')"
                />
            @else
                <div class="overflow-hidden rounded-2xl ring-1 ring-brand/10 bg-white">
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
                            <thead class="bg-brand-muted/30 text-left text-[10px] uppercase tracking-widest text-gray-500">
                                <tr>
                                    <th class="px-5 py-3.5 font-semibold">{{ __('borrower.payments_page.col_date') }}</th>
                                    <th class="px-5 py-3.5 font-semibold">{{ __('borrower.payments_page.col_reference') }}</th>
                                    <th class="px-5 py-3.5 font-semibold">{{ __('borrower.payments_page.col_type') }}</th>
                                    <th class="px-5 py-3.5 font-semibold">{{ __('borrower.payments_page.col_amount') }}</th>
                                    <th class="px-5 py-3.5 font-semibold">{{ __('borrower.payments_page.col_status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($entries as $entry)
                                    @php
                                        $badge = match ($entry['status']) {
                                            'verified', 'paid' => 'bg-emerald-50 text-emerald-700',
                                            'rejected', 'cancelled' => 'bg-red-50 text-red-700',
                                            'clarification_requested', 'awaiting_payout' => 'bg-sky-50 text-sky-700',
                                            default => 'bg-amber-50 text-amber-800',
                                        };
                                    @endphp
                                    <tr class="hover:bg-brand-muted/20 cursor-pointer transition" onclick="window.location='{{ $entry['url'] }}'">
                                        <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">{{ $entry['date']?->format('d M Y') }}</td>
                                        <td class="px-5 py-3.5 font-mono text-xs font-semibold text-brand">{{ $entry['reference'] }}</td>
                                        <td class="px-5 py-3.5 text-gray-800">{{ $entry['type_label'] }}</td>
                                        <td class="px-5 py-3.5 font-semibold tabular-nums text-gray-900">{{ format_money($entry['amount']) }}</td>
                                        <td class="px-5 py-3.5">
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
        </section>
    </x-site.page-loading-shell>

</x-site.borrower-layout>
