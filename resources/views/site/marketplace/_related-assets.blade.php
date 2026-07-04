@props(['assets', 'title' => null, 'authenticated' => false])

@if (($assets ?? collect())->isNotEmpty())
    <section class="mt-10 pt-8 border-t border-gray-100">
        <h2 class="text-lg font-bold text-gray-900 mb-4">{{ $title ?? __('site.marketplace.related_assets') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
