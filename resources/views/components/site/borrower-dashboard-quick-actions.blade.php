@props([
    'activeLoan' => null,
    'supportUnread' => 0,
    'messagesUnread' => 0,
    'paymentsBadge' => null,
])

@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
    $actions = [
        [
            'key' => 'apply',
            'label' => $isSw ? 'Omba Mkopo' : 'Apply for Loan',
            'route' => route('site.borrower.loan-products'),
            'icon' => '➕',
            'badge' => null,
        ],
        [
            'key' => 'payments',
            'label' => $isSw ? 'Malipo' : 'Payments',
            'route' => $activeLoan
                ? route('site.borrower.payments.create', ['loan' => $activeLoan->id])
                : route('site.borrower.payments'),
            'icon' => '💸',
            'badge' => $paymentsBadge,
        ],
        [
            'key' => 'support',
            'label' => $isSw ? 'Msaada' : 'Support',
            'route' => route('site.borrower.support'),
            'icon' => '💬',
            'badge' => ((int) $supportUnread) > 0 ? (int) $supportUnread : null,
        ],
        [
            'key' => 'messages',
            'label' => $isSw ? 'Ujumbe' : 'Messages',
            'route' => route('site.borrower.messages'),
            'icon' => '✉️',
            'badge' => ((int) $messagesUnread) > 0 ? (int) $messagesUnread : null,
        ],
        [
            'key' => 'statement',
            'label' => $isSw ? 'Taarifa' : 'Statement',
            'route' => route('site.borrower.payments'),
            'icon' => '📄',
            'badge' => null,
        ],
        [
            'key' => 'profile',
            'label' => $isSw ? 'Wasifu' : 'Profile',
            'route' => route('site.borrower.profile'),
            'icon' => '👤',
            'badge' => null,
        ],
    ];
@endphp

<section class="mb-6">
    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3 kf-muted-label">{{ __('borrower.dashboard.quick_actions_title') }}</p>
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 sm:gap-3">
        @foreach ($actions as $action)
            <a href="{{ $action['route'] }}"
               class="kf-action-tile group relative flex flex-col items-center gap-2 rounded-2xl glass-card px-2 py-4 text-center ring-1 ring-gray-200/80 hover:ring-brand/30 hover:shadow-sm transition">
                @if (! empty($action['badge']))
                    <span class="absolute top-1.5 right-1.5 min-w-[1.15rem] h-5 px-1 rounded-full bg-brand-gold text-brand text-[10px] font-bold grid place-items-center tabular-nums">{{ $action['badge'] }}</span>
                @endif
                <span class="text-2xl leading-none group-hover:scale-110 transition-transform" aria-hidden="true">{{ $action['icon'] }}</span>
                <span class="text-[11px] sm:text-xs font-semibold text-gray-800 leading-tight">{{ $action['label'] }}</span>
            </a>
        @endforeach
    </div>
</section>
