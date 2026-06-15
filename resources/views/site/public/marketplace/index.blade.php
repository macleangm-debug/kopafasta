<x-site.layout :title="brand_title(__('borrower.marketplace.title'))">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ brand_name() }} Asset Marketplace</p>
                <h1 class="text-3xl font-bold tracking-tight">{{ __('borrower.marketplace.title') }}</h1>
                <p class="text-sm text-gray-500 mt-2">{{ __('borrower.marketplace.subtitle') }}</p>
            </div>
            @guest
                <a href="{{ route('site.login', ['redirect' => route('site.marketplace')]) }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    Log in to apply
                </a>
            @else
                <a href="{{ route('site.borrower.marketplace') }}" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                    My marketplace →
                </a>
            @endguest
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <a href="{{ route('site.marketplace', request()->except('category')) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ empty($category) ? 'bg-amber-500 text-gray-900' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">
                {{ __('borrower.marketplace.all') }}
            </a>
            @foreach ($categories as $key => $label)
                <a href="{{ route('site.marketplace', array_merge(request()->except('category'), ['category' => $key])) }}"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $category === $key ? 'bg-amber-500 text-gray-900' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('site.marketplace') }}" class="mb-6 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 bg-white rounded-xl ring-1 ring-gray-200 p-4">
            @if ($category)
                <input type="hidden" name="category" value="{{ $category }}">
            @endif
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.search') }}</label>
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search title…" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.min_price') }}</label>
                <input type="text" inputmode="decimal" name="min_price" data-money-input="0" value="{{ \App\Support\MoneyFormat::forInput($filters['min_price'] ?? '') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.max_price') }}</label>
                <input type="text" inputmode="decimal" name="max_price" data-money-input="0" value="{{ \App\Support\MoneyFormat::forInput($filters['max_price'] ?? '') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2 lg:col-span-5 flex gap-2">
                <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-sm">{{ __('borrower.marketplace.apply_filters') }}</button>
                <a href="{{ route('site.marketplace', $category ? ['category' => $category] : []) }}" class="px-4 py-2 text-sm text-gray-600 hover:underline">{{ __('borrower.marketplace.clear') }}</a>
            </div>
        </form>

        @if ($assets->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-200 p-12 text-center text-gray-500">
                <p class="text-4xl mb-3">🏷️</p>
                <p class="font-semibold">{{ __('borrower.marketplace.empty_title') }}</p>
                <p class="text-sm mt-1">{{ __('borrower.marketplace.empty_desc') }}</p>
            </div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($assets as $asset)
                    <article class="bg-white rounded-2xl border border-gray-200 overflow-hidden flex flex-col">
                        @if (! empty($asset['photos'][0]))
                            <img src="{{ Storage::url($asset['photos'][0]) }}" alt="" class="aspect-[4/3] object-cover bg-slate-100">
                        @else
                            <div class="aspect-[4/3] bg-gradient-to-br from-slate-100 to-slate-200 grid place-items-center text-4xl">🚗</div>
                        @endif
                        <div class="p-5 flex-1 flex flex-col">
                            <p class="text-xs uppercase tracking-widest text-gray-400">{{ $categories[$asset['category']] ?? $asset['category'] }}</p>
                            <h2 class="font-semibold text-gray-900 mt-1">{{ $asset['title'] }}</h2>
                            <dl class="mt-4 space-y-1 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.asset_value') }}</dt><dd class="font-semibold">{{ format_money($asset['asset_value'] ?? 0) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.deposit') }}</dt><dd class="font-semibold">{{ format_money($asset['deposit']) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.weekly_installment') }}</dt><dd class="font-semibold">{{ format_money($asset['weekly_installment']) }}</dd></div>
                            </dl>
                            <div class="mt-5">
                                <a href="{{ route('site.marketplace.show', $asset['id']) }}" class="text-sm font-semibold text-amber-700 hover:underline">View details →</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>
