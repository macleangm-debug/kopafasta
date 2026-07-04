@props(['products', 'current' => null])

@if (($products ?? collect())->isNotEmpty())
    <section class="bg-[#faf8f5] border-t border-gray-100 py-12 lg:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-6">{{ __('site.product_detail.other_products') }}</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach ($products as $other)
                    @if (is_marketplace_loan_product($other->code))
                        @continue
                    @endif
                    <x-site.product-card :product="$other" />
                @endforeach
            </div>
            <div class="mt-8 text-center">
                <a href="{{ route('site.products') }}" class="text-sm font-semibold text-brand hover:underline">
                    {{ __('site.nav.all_products') }} →
                </a>
            </div>
        </div>
    </section>
@endif
