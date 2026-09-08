<x-site.borrower-layout :title="brand_title(__('borrower.loan_products_page.title'))" active="loans" content-width="wide">
    @php
        $categoryOptions = [
            'individual' => __('borrower.loan_products_page.categories.individual'),
            'group' => __('borrower.loan_products_page.categories.group'),
            'asset' => __('borrower.loan_products_page.categories.asset'),
            'business' => __('borrower.loan_products_page.categories.business'),
            'agriculture' => __('borrower.loan_products_page.categories.agriculture'),
            'education' => __('borrower.loan_products_page.categories.education'),
        ];
        // Prefer catalogue categories when present; keep brief order for known keys.
        $ordered = collect($categoryOptions)->keys()
            ->merge($categories ?? [])
            ->unique()
            ->values();
    @endphp
    <div x-data="loanProductsPage()">
        <section class="relative overflow-hidden rounded-2xl premium-gradient border border-gray-100/80 mb-8">
            <div class="px-6 sm:px-8 py-8 sm:py-10">
                <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ brand_name() }} {{ __('borrower.apply.smart_application') }}</p>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-brand">{{ __('borrower.loan_products_page.title') }}</h1>
                <p class="mt-2 text-sm sm:text-base text-gray-600 max-w-2xl">{{ __('borrower.loan_products_page.subtitle') }}</p>
            </div>
        </section>

        {{-- Profile completeness is enforced only at final submit — never as a product-list hurdle. --}}

        <div class="mb-5">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative min-w-0">
                    <label for="loan-product-search" class="sr-only">{{ __('borrower.loan_products_page.search_label') }}</label>
                    <input id="loan-product-search"
                           type="search"
                           x-model="search"
                           placeholder="{{ __('borrower.loan_products_page.search_placeholder') }}"
                           class="w-full rounded-xl border-gray-200 bg-white pl-10 pr-4 py-3 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand shadow-sm">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="9" cy="9" r="5.5"/><path d="M14 14l3 3"/>
                    </svg>
                </div>

                {{-- Mobile: premium bottom sheet (never native select) --}}
                <div class="sm:hidden">
                    <button type="button"
                            @click="categoriesOpen = true"
                            class="w-full inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition">
                        <svg class="w-4 h-4 text-brand shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h6"/></svg>
                        <span class="truncate" x-text="categoryLabel()"></span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 ml-auto" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                </div>

                {{-- Desktop: premium dropdown (not native mobile select) --}}
                <div class="hidden sm:block sm:w-56 shrink-0 relative" @keydown.escape.window="categoryMenuOpen = false">
                    <button type="button"
                            @click="categoryMenuOpen = !categoryMenuOpen"
                            class="w-full inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition capitalize">
                        <span class="truncate" x-text="categoryLabel()"></span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 ml-auto" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <div x-show="categoryMenuOpen"
                         x-cloak
                         @click.outside="categoryMenuOpen = false"
                         class="absolute right-0 mt-2 w-full rounded-2xl bg-white ring-1 ring-gray-200 shadow-lg overflow-hidden z-40">
                        <button type="button"
                                @click="selectCategory('all')"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-left transition"
                                :class="category === 'all' ? 'bg-brand text-white' : 'text-gray-800 hover:bg-brand-muted/40'">
                            <span>{{ __('borrower.loan_products_page.all_categories') }}</span>
                            <svg x-show="category === 'all'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                        </button>
                        @foreach ($ordered as $cat)
                            @php
                                $label = $categoryOptions[$cat] ?? str_replace('_', ' ', (string) $cat);
                            @endphp
                            <button type="button"
                                    @click="selectCategory(@js($cat))"
                                    class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-left transition capitalize"
                                    :class="category === @js($cat) ? 'bg-brand text-white' : 'text-gray-800 hover:bg-brand-muted/40'">
                                <span>{{ $label }}</span>
                                <svg x-show="category === @js($cat)" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <x-site.bottom-sheet :title="__('borrower.loan_products_page.filter_label')" open="categoriesOpen">
            <div class="grid gap-2">
                <button type="button"
                        @click="selectCategory('all')"
                        class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition"
                        :class="category === 'all' ? 'bg-brand text-white' : 'bg-gray-50 text-gray-800 hover:bg-brand-muted/40'">
                    <span>{{ __('borrower.loan_products_page.all_categories') }}</span>
                    <svg x-show="category === 'all'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                </button>
                @foreach ($ordered as $cat)
                    @php
                        $label = $categoryOptions[$cat] ?? str_replace('_', ' ', (string) $cat);
                    @endphp
                    <button type="button"
                            @click="selectCategory(@js($cat))"
                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition capitalize"
                            :class="category === @js($cat) ? 'bg-brand text-white' : 'bg-gray-50 text-gray-800 hover:bg-brand-muted/40'">
                        <span>{{ $label }}</span>
                        <svg x-show="category === @js($cat)" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                    </button>
                @endforeach
            </div>
        </x-site.bottom-sheet>

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
                         x-show="matchesWrapper($el)">
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
            const labels = @js(array_merge(
                ['all' => __('borrower.loan_products_page.all_categories')],
                collect($ordered)->mapWithKeys(fn ($cat) => [
                    $cat => $categoryOptions[$cat] ?? str_replace('_', ' ', (string) $cat),
                ])->all()
            ));
            return {
                search: '',
                category: 'all',
                categoriesOpen: false,
                categoryMenuOpen: false,
                visibleCount: {{ $products->count() }},
                init() {
                    this.$watch('search', () => this.refreshVisibleCount());
                    this.$watch('category', () => this.refreshVisibleCount());
                },
                categoryLabel() {
                    return labels[this.category] || this.category;
                },
                selectCategory(value) {
                    this.category = value || 'all';
                    this.categoriesOpen = false;
                    this.categoryMenuOpen = false;
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
