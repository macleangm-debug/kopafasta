<x-site.borrower-layout :title="brand_title(__('borrower.loan_products_page.title'))" active="loans" content-width="wide">
    <div x-data="loanProductsPage()" x-cloak>
        <section class="relative overflow-hidden rounded-2xl premium-gradient border border-gray-100/80 mb-8">
            <div class="px-6 sm:px-8 py-8 sm:py-10">
                <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ brand_name() }} {{ __('borrower.apply.smart_application') }}</p>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-brand">{{ __('borrower.loan_products_page.title') }}</h1>
                <p class="mt-2 text-sm sm:text-base text-gray-600 max-w-2xl">{{ __('borrower.loan_products_page.subtitle') }}</p>
            </div>
        </section>




        {{-- Profile completeness is enforced only at final submit — never as a product-list hurdle. --}}

        <div class="glass-card p-4 sm:p-5 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                <div class="flex-1 relative">
                    <label for="loan-product-search" class="sr-only">{{ __('borrower.loan_products_page.search_label') }}</label>
                    <input id="loan-product-search"
                           type="search"
                           x-model="search"
                           placeholder="{{ __('borrower.loan_products_page.search_placeholder') }}"
                           class="w-full rounded-xl border-gray-200 bg-white/80 pl-10 pr-4 py-3 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="9" cy="9" r="5.5"/><path d="M14 14l3 3"/>
                    </svg>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button"
                            @click="category = 'all'"
                            :class="category === 'all' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-600 ring-gray-200 hover:ring-brand/30'"
                            class="rounded-full px-4 py-2 text-xs font-semibold ring-1 transition">
                        {{ __('borrower.loan_products_page.all_categories') }}
                    </button>
                    @foreach ($categories as $cat)
                        <button type="button"
                                @click="category = @js($cat)"
                                :class="category === @js($cat) ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-600 ring-gray-200 hover:ring-brand/30'"
                                class="rounded-full px-4 py-2 text-xs font-semibold ring-1 transition capitalize">
                            {{ str_replace('_', ' ', $cat) }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($products->isEmpty())
            <x-site.empty-state
                icon="📋"
                :title="__('borrower.dashboard_page.no_products')"
                :description="__('borrower.loan_products_page.subtitle')"
            />
        @else
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5 lg:gap-6">
                @foreach ($products as $product)
                    @php
                        $cardDescription = loan_product_card_description($product);
                        $cardSearch = strtolower($product->code.' '.$product->localizedName().' '.$cardDescription);
                        $cardCategory = (string) ($product->category ?: 'general');
                    @endphp
                    <div data-product-wrapper
                         data-category="{{ $cardCategory }}"
                         data-search="{{ $cardSearch }}"
                         x-show="matchesWrapper($el)"
                         x-transition.opacity.duration.200ms>
                        <x-site.premium-loan-product-card :product="$product" :customer="$customer" />
                    </div>
                @endforeach
            </div>

            <div x-show="visibleCount === 0" x-cloak class="mt-8">
                <x-site.empty-state
                    icon="🔍"
                    :title="__('borrower.loan_products_page.no_results_title')"
                    :description="__('borrower.loan_products_page.no_results_body')"
                />
            </div>

            <div class="mt-10 glass-card p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ __('borrower.loan_products_page.help_title') }}</h2>
                    <p class="text-sm text-gray-600 mt-1 max-w-xl">{{ __('borrower.loan_products_page.help_body') }}</p>
                </div>
                <a href="{{ route('site.borrower.support') }}"
                   class="inline-flex justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm shrink-0">
                    {{ __('borrower.nav.support') }} →
                </a>
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function loanProductsPage() {
            return {
                search: '',
                category: 'all',
                visibleCount: {{ $products->count() }},
                init() {
                    this.$watch('search', () => this.refreshVisibleCount());
                    this.$watch('category', () => this.refreshVisibleCount());
                },
                matchesWrapper(el) {
                    const haystack = (el.dataset.search || '').toLowerCase();
                    const cat = el.dataset.category || '';
                    const q = (this.search || '').trim().toLowerCase();
                    const categoryOk = this.category === 'all' || cat === this.category;
                    const searchOk = ! q || haystack.includes(q);
                    return categoryOk && searchOk;
                },
                refreshVisibleCount() {
                    this.$nextTick(() => {
                        const wrappers = this.$root.querySelectorAll('[data-product-wrapper]');
                        let count = 0;
                        wrappers.forEach((wrapper) => {
                            if (this.matchesWrapper(wrapper)) count++;
                        });
                        this.visibleCount = count;
                    });
                },
            };
        }
    </script>
    @endpush
</x-site.borrower-layout>
