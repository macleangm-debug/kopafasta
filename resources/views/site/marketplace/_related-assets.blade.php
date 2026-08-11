@props(['assets', 'title' => null, 'authenticated' => false])

@if (($assets ?? collect())->isNotEmpty())
    <section class="mt-10 pt-8 border-t border-gray-100">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <h2 class="text-lg font-bold text-gray-900">{{ $title ?? __('site.marketplace.related_assets') }}</h2>
            <a href="{{ $authenticated ? route('site.borrower.marketplace') : route('site.marketplace') }}"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline">
                {{ __('borrower.marketplace.view_all_marketplace') }}
                <span aria-hidden="true">→</span>
            </a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 items-stretch">
            @foreach ($assets as $related)
                @include('site.marketplace._asset-card', [
                    'asset' => $related,
                    'categories' => config('asset_marketplace.categories', []),
                    'showUrl' => $authenticated
                        ? route('site.borrower.marketplace.show', $related['id'])
                        : route('site.marketplace.show', $related['id']),
                    'authenticated' => $authenticated,
                ])
            @endforeach
        </div>
    </section>
@endif
