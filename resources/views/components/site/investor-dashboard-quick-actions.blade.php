@php
    $actions = [
        [
            'label' => 'Browse pools',
            'url' => route('site.investor.pools'),
            'icon' => 'layers',
            'accent' => 'from-emerald-500 to-emerald-700',
        ],
        [
            'label' => 'Deposit funds',
            'url' => route('site.investor.wallet'),
            'icon' => 'wallet',
            'accent' => 'from-slate-700 to-slate-900',
        ],
        [
            'label' => 'Returns',
            'url' => route('site.investor.returns'),
            'icon' => 'trend',
            'accent' => 'from-sky-500 to-sky-700',
        ],
        [
            'label' => 'Analytics',
            'url' => route('site.investor.analytics'),
            'icon' => 'pie',
            'accent' => 'from-violet-500 to-violet-700',
        ],
    ];
@endphp

<div class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    @foreach ($actions as $action)
        <a href="{{ $action['url'] }}"
           class="group relative overflow-hidden rounded-2xl border ring-1 ring-brand/10 glass-card p-4 sm:p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200">
            <div class="absolute -right-4 -top-4 size-20 rounded-full bg-gradient-to-br {{ $action['accent'] }} opacity-10 group-hover:opacity-20 transition-opacity"></div>
            <div class="relative flex flex-col gap-3">
                <span class="size-10 rounded-xl bg-gradient-to-br {{ $action['accent'] }} text-white grid place-items-center shadow-sm">
                    @if ($action['icon'] === 'layers')
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 2 8l10 5 10-5-10-5zM2 14l10 5 10-5M2 19l10 5 10-5"/></svg>
                    @elseif ($action['icon'] === 'wallet')
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zm0 0V5a2 2 0 0 1 2-2h11M16 13h2"/></svg>
                    @elseif ($action['icon'] === 'trend')
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17l6-6 4 4 8-8M21 7h-5M21 7v5"/></svg>
                    @else
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12A9 9 0 1 1 12 3v9h9z"/></svg>
                    @endif
                </span>
                <span class="text-sm font-bold text-gray-900 leading-tight">{{ $action['label'] }}</span>
            </div>
        </a>
    @endforeach
</div>
