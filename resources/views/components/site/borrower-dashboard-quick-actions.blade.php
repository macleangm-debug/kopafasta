@props(['activeLoan' => null])

@php
    $actions = [
        [
            'label' => __('borrower.dashboard.quick_actions.apply'),
            'route' => route('site.borrower.dashboard').'#loan-products',
            'icon'  => '➕',
        ],
        [
            'label' => __('borrower.dashboard.quick_actions.payments'),
            'route' => $activeLoan
                ? route('site.borrower.payments.create', ['loan' => $activeLoan->id])
                : route('site.borrower.payments'),
            'icon'  => '💸',
        ],
        [
            'label' => __('borrower.dashboard.quick_actions.collaterals'),
            'route' => route('site.borrower.profile', ['section' => 'assets']),
            'icon'  => '🏠',
        ],
        [
            'label' => __('borrower.dashboard.quick_actions.referral'),
            'route' => route('site.borrower.referrals'),
            'icon'  => '🎁',
        ],
        [
            'label' => __('borrower.dashboard.quick_actions.statements'),
            'route' => route('site.borrower.loans'),
            'icon'  => '📄',
        ],
        [
            'label' => __('borrower.dashboard.quick_actions.support'),
            'route' => route('site.borrower.support'),
            'icon'  => '💬',
        ],
    ];
@endphp

<section class="mb-6">
    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('borrower.dashboard.quick_actions_title') }}</p>
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 sm:gap-3">
        @foreach ($actions as $action)
            <a href="{{ $action['route'] }}"
               class="group flex flex-col items-center gap-2 rounded-2xl glass-card px-2 py-4 text-center ring-1 ring-gray-200/80 hover:ring-brand/30 hover:shadow-sm transition">
                <span class="text-2xl leading-none group-hover:scale-110 transition-transform" aria-hidden="true">{{ $action['icon'] }}</span>
                <span class="text-[11px] sm:text-xs font-semibold text-gray-800 leading-tight">{{ $action['label'] }}</span>
            </a>
        @endforeach
    </div>
</section>
