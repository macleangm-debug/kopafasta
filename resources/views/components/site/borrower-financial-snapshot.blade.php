@props(['snapshot'])

<section class="mb-6 glass-card overflow-hidden ring-1 ring-brand/10">
    <div class="px-5 sm:px-6 py-4 border-b border-gray-100/80 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="text-xl" aria-hidden="true">💳</span>
            <h2 class="font-semibold text-gray-900">{{ __('borrower.dashboard.snapshot.title') }}</h2>
        </div>
    </div>
    <div class="p-5 sm:p-6">
        <div class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-1 -mx-1 px-1 scrollbar-none lg:grid lg:grid-cols-3 lg:overflow-visible lg:pb-0">
            @foreach ($snapshot as $item)
                @php
                    $status = $item['status'] ?? null;
                    $valueClass = match ($status) {
                        'active' => 'text-emerald-700',
                        'inactive' => 'text-amber-700',
                        default => 'text-gray-900',
                    };
                    $shell = 'min-w-[78%] sm:min-w-[46%] lg:min-w-0 snap-start rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-4';
                @endphp
                @if (! empty($item['url']))
                    <a href="{{ $item['url'] }}" class="{{ $shell }} hover:ring-brand/30 hover:shadow-sm transition block">
                @else
                    <div class="{{ $shell }}">
                @endif
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold leading-tight">{{ $item['label'] }}</p>
                        <span class="text-lg leading-none shrink-0" aria-hidden="true">{{ $item['icon'] ?? '📋' }}</span>
                    </div>
                    <p class="mt-2 text-base font-bold tabular-nums truncate {{ $valueClass }}">{{ $item['value'] }}</p>
                    @if (! empty($item['hint']))
                        <p class="text-xs text-gray-500 mt-0.5">{{ $item['hint'] }}</p>
                    @endif
                @if (! empty($item['url']))
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
