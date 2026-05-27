<x-site.layout title="Loan Products — Kopafasta">
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-2">Catalogue</p>
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">All loan products</h1>
        <p class="mt-3 text-gray-600 max-w-2xl">Pick the one that matches your need. All applications go through the same fast, secure wizard.</p>

        <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($products as $product)
                <a href="{{ route('site.product', $product->code) }}"
                   class="group block rounded-2xl border border-gray-200 hover:border-amber-400 hover:shadow-lg transition p-6 bg-white">
                    <div class="flex items-start justify-between mb-3">
                        <span class="inline-flex items-center text-[11px] font-mono font-semibold text-amber-700 bg-amber-50 px-2 py-1 rounded">{{ $product->code }}</span>
                        <span class="text-xs text-gray-500">from <span class="font-bold text-gray-900">{{ number_format($product->interest_rate * 100, 1) }}%</span> / mo</span>
                    </div>
                    <h3 class="text-lg font-semibold group-hover:text-amber-700">{{ $product->name }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $product->description }}</p>
                    <div class="mt-4 text-xs text-gray-500 flex items-center justify-between">
                        <span>{{ number_format($product->min_amount / 1000) }}k – {{ number_format($product->max_amount / 1000000, 0) }}M TZS</span>
                        <span>{{ $product->tenure_min_months }}–{{ $product->tenure_max_months }} mo</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
</x-site.layout>
