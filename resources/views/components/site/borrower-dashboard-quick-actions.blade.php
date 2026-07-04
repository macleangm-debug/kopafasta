@props(['activeLoan' => null])

@php
    $actions = [
        [
            'key' => 'apply',
            'label' => __('borrower.dashboard.quick_actions.apply'),
            'url' => route('site.products'),
            'icon' => 'apply',
            'accent' => 'from-brand to-brand-light text-white',
        ],
        [
            'key' => 'marketplace',
            'label' => __('borrower.dashboard.quick_actions.marketplace'),
            'url' => route('site.borrower.marketplace'),
            'icon' => 'marketplace',
            'accent' => 'from-sky-500 to-sky-700 text-white',
        ],
        [
            'key' => 'payments',
            'label' => __('borrower.dashboard.quick_actions.payments'),
            'url' => $activeLoan
                ? route('site.borrower.payments.create', ['loan' => $activeLoan->id])
                : route('site.borrower.loans'),
            'icon' => 'payments',
            'accent' => 'from-emerald-500 to-emerald-700 text-white',
        ],
        [
            'key' => 'profile',
            'label' => __('borrower.dashboard.quick_actions.profile'),
            'url' => route('site.borrower.profile'),
            'icon' => 'profile',
            'accent' => 'from-violet-500 to-violet-700 text-white',
        ],
    ];
@endphp

<div class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    @foreach ($actions as $action)
        <a href="{{ $action['url'] }}"
           class="group relative overflow-hidden rounded-2xl glass-card p-4 sm:p-5 hover:shadow-[0_12px_40px_rgba(0,77,64,0.12)] hover:-translate-y-0.5 transition-all duration-200">
            <div class="absolute -right-4 -top-4 size-20 rounded-full bg-gradient-to-br {{ $action['accent'] }} opacity-10 group-hover:opacity-20 transition-opacity"></div>
            <div class="relative flex flex-col gap-3">
                <span class="size-10 rounded-xl bg-gradient-to-br {{ $action['accent'] }} grid place-items-center shadow-sm">
                    @if ($action['icon'] === 'apply')
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                    @elseif ($action['icon'] === 'marketplace')
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                    @elseif ($action['icon'] === 'payments')
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    @else
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
                    @endif
                </span>
                <span class="text-sm font-bold text-gray-900 leading-tight">{{ $action['label'] }}</span>
            </div>
        </a>
    @endforeach
</div>
