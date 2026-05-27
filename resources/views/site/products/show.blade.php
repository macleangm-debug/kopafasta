<x-site.layout :title="$product->name . ' — Kopafasta'">
    <section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <a href="{{ route('site.products') }}" class="text-sm text-gray-500 hover:text-amber-600 inline-flex items-center gap-1">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 10H4m4 4-4-4 4-4"/></svg>
            All products
        </a>
        <div class="mt-4 flex items-start justify-between gap-6 flex-wrap">
            <div>
                <span class="inline-flex items-center text-[11px] font-mono font-semibold text-amber-700 bg-amber-50 px-2 py-1 rounded">{{ $product->code }}</span>
                <h1 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight">{{ $product->name }}</h1>
                <p class="mt-3 text-gray-600 max-w-2xl">{{ $product->description }}</p>
            </div>
            <a href="{{ route('site.apply.show', ['product' => $product->id]) }}"
               class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full shadow-sm">
                Apply for this loan
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
            </a>
        </div>

        <div class="mt-10 grid sm:grid-cols-3 gap-4">
            <div class="rounded-2xl border border-gray-200 p-5">
                <div class="text-[11px] uppercase tracking-wider text-gray-500">Interest</div>
                <div class="text-2xl font-bold mt-1">{{ number_format($product->interest_rate * 100, 1) }}<span class="text-base font-medium text-gray-500">% / mo</span></div>
            </div>
            <div class="rounded-2xl border border-gray-200 p-5">
                <div class="text-[11px] uppercase tracking-wider text-gray-500">Amount</div>
                <div class="text-2xl font-bold mt-1">{{ number_format($product->min_amount / 1000) }}k <span class="text-base text-gray-500">– {{ number_format($product->max_amount / 1000000, 0) }}M</span></div>
                <div class="text-xs text-gray-500">TZS</div>
            </div>
            <div class="rounded-2xl border border-gray-200 p-5">
                <div class="text-[11px] uppercase tracking-wider text-gray-500">Tenure</div>
                <div class="text-2xl font-bold mt-1">{{ $product->tenure_min_months }}–{{ $product->tenure_max_months }} <span class="text-base font-medium text-gray-500">months</span></div>
            </div>
        </div>

        <div class="mt-8 rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-lg mb-3">Requirements</h3>
            <ul class="space-y-2 text-sm text-gray-700">
                <li class="flex items-center gap-2"><span class="size-1.5 rounded-full bg-emerald-500"></span> Verified phone number and National ID (NIDA)</li>
                <li class="flex items-center gap-2"><span class="size-1.5 rounded-full bg-emerald-500"></span> Active Kopafasta account</li>
                @if ($product->requires_collateral)
                    <li class="flex items-center gap-2"><span class="size-1.5 rounded-full bg-amber-500"></span> Collateral document (logbook, title or asset proof)</li>
                @endif
                @if ($product->requires_guarantor)
                    <li class="flex items-center gap-2"><span class="size-1.5 rounded-full bg-amber-500"></span> Guarantor(s) — provided during application</li>
                @endif
                <li class="flex items-center gap-2"><span class="size-1.5 rounded-full bg-emerald-500"></span> Proof of income or business activity</li>
            </ul>
        </div>
    </section>
</x-site.layout>
