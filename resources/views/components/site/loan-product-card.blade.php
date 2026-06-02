@props(['product'])

@php
    $theme = loan_product_theme($product->code);
    $displayedRate = app(\App\Services\DisplayedRateService::class)->displayedMonthlyRate($product);
    $themeClasses = match ($theme['theme'] ?? 'slate') {
        'emerald' => 'from-emerald-500 to-emerald-700 text-white',
        'indigo'  => 'from-indigo-500 to-indigo-700 text-white',
        'violet'  => 'from-violet-500 to-violet-700 text-white',
        'sky'     => 'from-sky-500 to-sky-700 text-white',
        'amber'   => 'from-amber-400 to-amber-600 text-gray-900',
        'orange'  => 'from-orange-500 to-orange-700 text-white',
        'blue'    => 'from-blue-500 to-blue-700 text-white',
        'rose'    => 'from-rose-500 to-rose-700 text-white',
        'pink'    => 'from-pink-500 to-pink-700 text-white',
        'cyan'    => 'from-cyan-500 to-cyan-700 text-white',
        default   => 'from-slate-600 to-slate-800 text-white',
    };
@endphp

<div class="snap-start shrink-0 w-[min(85vw,320px)] bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
    <div class="bg-gradient-to-br {{ $themeClasses }} px-5 py-4">
        <div class="flex items-center gap-3">
            <span class="text-3xl" aria-hidden="true">{{ $theme['icon'] ?? '💼' }}</span>
            <div class="min-w-0">
                <p class="text-[10px] font-mono font-semibold opacity-80">{{ $product->code }}</p>
                <h3 class="text-lg font-bold leading-tight truncate">{{ $product->name }}</h3>
            </div>
        </div>
    </div>
    <button type="button" @click="open = open === {{ $product->id }} ? null : {{ $product->id }}"
            class="text-left p-5 flex-1 hover:bg-gray-50 transition">
        <p class="text-sm text-gray-600 line-clamp-3">{{ $product->description ?: 'Collateral-driven lending product.' }}</p>
        <p class="text-sm font-semibold text-gray-900 mt-3">{{ format_money($product->min_amount) }} – {{ format_money($product->max_amount, false) }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ __('borrower.apply.details.monthly_rate') }} {{ number_format($displayedRate * 100, 1) }}% · {{ $product->tenure_min_months }}–{{ $product->tenure_max_months }} {{ __('borrower.apply.details.months') }}</p>
    </button>
    <div x-show="open === {{ $product->id }}" x-transition x-cloak class="border-t border-gray-100 px-5 pb-5">
        <dl class="grid gap-2 text-sm pt-4">
            <div><dt class="text-gray-500">{{ __('borrower.apply.details.monthly_rate') }}</dt><dd class="font-medium">{{ number_format($displayedRate * 100, 2) }}% {{ __('borrower.apply.browse.per_month') }}</dd></div>
            <div><dt class="text-gray-500">Tenure</dt><dd class="font-medium">{{ $product->tenure_min_months }}–{{ $product->tenure_max_months }} months</dd></div>
        </dl>
        <a href="{{ route('site.borrower.apply', ['product' => $product->id]) }}"
           class="mt-4 inline-flex w-full justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
            Apply for {{ $product->name }} →
        </a>
    </div>
</div>
