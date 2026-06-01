<x-site.borrower-layout :title="brand_title(__('borrower.marketplace.title'))" active="marketplace">

    <h1 class="text-2xl font-bold mb-1">{{ __('borrower.marketplace.title') }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ __('borrower.marketplace.subtitle') }}</p>

    {{-- Find what you need (collapsed) --}}
    <div class="mb-8 bg-gradient-to-br from-amber-50 to-white rounded-2xl border border-amber-100 overflow-hidden" x-data="{ requestOpen: false }">
        <button type="button" @click="requestOpen = !requestOpen" class="w-full text-left p-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-lg">{{ __('borrower.marketplace.request_collapsed_title') }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ __('borrower.marketplace.find_subtitle') }}</p>
            </div>
            <span class="shrink-0 text-sm font-semibold text-amber-700" x-text="requestOpen ? '−' : '+'"></span>
        </button>
        <form x-show="requestOpen" x-cloak method="POST" action="{{ route('site.borrower.marketplace.request') }}" enctype="multipart/form-data" class="px-6 pb-6 grid sm:grid-cols-2 gap-4 border-t border-amber-100 pt-4">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.asset_name') }}</label>
                <input name="asset_name" required placeholder="e.g. Toyota Hilux 2019" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.budget') }}</label>
                <input type="number" name="budget" min="0" step="1000" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.tenure') }}</label>
                <input type="number" name="preferred_tenure_months" min="1" max="120" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.photo') }}</label>
                <input type="file" name="photo" accept="image/*" capture="environment" class="w-full text-sm">
            </div>
            <div class="sm:col-span-2">
                <button class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.marketplace.submit_request') }}</button>
            </div>
        </form>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('site.borrower.marketplace', request()->except('category')) }}"
           class="px-3 py-1.5 rounded-lg text-sm font-medium {{ empty($category) ? 'bg-amber-500 text-gray-900' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">
            {{ __('borrower.marketplace.all') }}
        </a>
        @foreach ($categories as $key => $label)
            <a href="{{ route('site.borrower.marketplace', array_merge(request()->except('category'), ['category' => $key])) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $category === $key ? 'bg-amber-500 text-gray-900' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('site.borrower.marketplace') }}" class="mb-6 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 bg-white rounded-xl ring-1 ring-gray-200 p-4">
        @if ($category)
            <input type="hidden" name="category" value="{{ $category }}">
        @endif
        <div class="lg:col-span-2">
            <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.search') }}</label>
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search title…" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.min_price') }}</label>
            <input type="number" name="min_price" value="{{ $filters['min_price'] ?? '' }}" min="0" step="1000" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.max_price') }}</label>
            <input type="number" name="max_price" value="{{ $filters['max_price'] ?? '' }}" min="0" step="1000" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-[11px] font-medium text-gray-500 mb-1">{{ __('borrower.marketplace.max_tenure') }}</label>
            <input type="number" name="tenure" value="{{ $filters['tenure'] ?? '' }}" min="1" max="120" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2 lg:col-span-5 flex gap-2">
            <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-sm">{{ __('borrower.marketplace.apply_filters') }}</button>
            <a href="{{ route('site.borrower.marketplace', $category ? ['category' => $category] : []) }}" class="px-4 py-2 text-sm text-gray-600 hover:underline">{{ __('borrower.marketplace.clear') }}</a>
        </div>
    </form>

    @if ($assets->isEmpty())
        <x-site.empty-state icon="🏷️" :title="__('borrower.marketplace.empty_title')" :description="__('borrower.marketplace.empty_desc')" />
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($assets as $asset)
                <article class="bg-white rounded-2xl border border-gray-200 overflow-hidden flex flex-col">
                    @if (! empty($asset['photos'][0]))
                        <img src="{{ Storage::url($asset['photos'][0]) }}" alt="" class="aspect-[4/3] object-cover bg-slate-100">
                    @else
                        <div class="aspect-[4/3] bg-gradient-to-br from-slate-100 to-slate-200 grid place-items-center text-4xl">
                            @switch($asset['category'])
                                @case('vehicles') 🚗 @break
                                @case('motorcycles') 🏍️ @break
                                @case('equipment') 🧰 @break
                                @default 🏭
                            @endswitch
                        </div>
                    @endif
                    <div class="p-5 flex-1 flex flex-col">
                        <p class="text-xs uppercase tracking-widest text-gray-400">{{ $categories[$asset['category']] ?? $asset['category'] }}</p>
                        <h2 class="font-semibold text-gray-900 mt-1">{{ $asset['title'] }}</h2>
                        <dl class="mt-4 space-y-1 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.asset_value') }}</dt><dd class="font-semibold">TZS {{ number_format($asset['asset_value'] ?? 0) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.deposit') }}</dt><dd class="font-semibold">TZS {{ number_format($asset['deposit']) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.weekly_installment') }}</dt><dd class="font-semibold">TZS {{ number_format($asset['weekly_installment']) }}</dd></div>
                            @if (! empty($asset['max_tenure_months']))
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.max_tenure') }}</dt><dd class="font-semibold">{{ $asset['max_tenure_months'] }} {{ __('borrower.apply.quote.months') }}</dd></div>
                            @endif
                        </dl>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="{{ route('site.borrower.marketplace.show', $asset['id']) }}" class="text-sm font-semibold text-amber-700 hover:underline">View asset</a>
                            <a href="{{ route('site.borrower.marketplace.show', $asset['id']) }}#apply" class="text-sm font-semibold text-gray-700 hover:underline">{{ __('borrower.marketplace.apply_asset') }}</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

</x-site.borrower-layout>
