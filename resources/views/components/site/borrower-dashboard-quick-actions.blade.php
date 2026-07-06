@props(['activeLoan' => null])

@php
    $actions = [
        [
            'label' => __('borrower.dashboard.quick_actions.apply'),
            'route' => route('site.borrower.dashboard').'#loan-products',
            'icon'  => '<path d="M12 5v14M5 12h14"/>',
            'accent' => 'bg-brand-gold text-brand hover:bg-yellow-400',
        ],
        [
            'label' => __('borrower.dashboard.quick_actions.marketplace'),
            'route' => route('site.borrower.marketplace'),
            'icon'  => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"/>',
            'accent' => 'bg-white text-brand ring-1 ring-brand/15 hover:bg-brand-muted/40',
        ],
        [
            'label' => __('borrower.dashboard.quick_actions.payments'),
            'route' => $activeLoan
                ? route('site.borrower.payments.create', ['loan' => $activeLoan->id])
                : route('site.borrower.payments'),
            'icon'  => '<path d="M3 10h18M5 6h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm3 9h3"/>',
            'accent' => 'bg-white text-brand ring-1 ring-brand/15 hover:bg-brand-muted/40',
        ],
        [
            'label' => __('borrower.dashboard.quick_actions.profile'),
            'route' => route('site.borrower.profile'),
            'icon'  => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0"/>',
            'accent' => 'bg-white text-brand ring-1 ring-brand/15 hover:bg-brand-muted/40',
        ],
    ];
@endphp

<div class="mb-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
    @foreach ($actions as $action)
        <a href="{{ $action['route'] }}"
           class="group flex flex-col items-center gap-2 rounded-2xl px-4 py-4 text-center text-sm font-semibold transition shadow-sm {{ $action['accent'] }}">
            <span class="size-10 rounded-xl grid place-items-center bg-black/5 group-hover:scale-105 transition-transform">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    {!! $action['icon'] !!}
                </svg>
            </span>
            <span class="leading-tight">{{ $action['label'] }}</span>
        </a>
    @endforeach
</div>
