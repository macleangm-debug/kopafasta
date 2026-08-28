<x-site.layout :title="brand_name().' — '.__('site.rewards.title')">
    <section class="relative overflow-hidden premium-gradient">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('site.rewards.title') }}</p>
            <h1 class="mt-3 text-3xl sm:text-5xl font-bold text-brand tracking-tight max-w-2xl">{{ __('site.rewards.kicker') }}</h1>
            <p class="mt-4 text-lg text-gray-600 max-w-xl">{{ __('site.rewards.intro') }}</p>
            @if (! empty($catalog[0]))
                <p class="mt-6 text-xl font-extrabold text-gray-900">
                    {{ __('site.rewards.example', ['points' => number_format($catalog[0]['points']), 'reward' => $catalog[0]['label']]) }}
                </p>
            @endif
            <a href="{{ route('site.register.borrower') }}" class="mt-8 inline-flex rounded-xl bg-brand text-white font-semibold px-6 py-3.5">{{ __('site.rewards.cta') }}</a>
        </div>
    </section>

    <section class="py-12 lg:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ __('site.rewards.qualify_title') }}</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($earn as $row)
                        <li class="flex justify-between gap-4 rounded-2xl ring-1 ring-gray-200 bg-white px-4 py-3">
                            <span class="text-sm font-medium text-gray-800">{{ $row['label'] }}</span>
                            <span class="text-sm font-black tabular-nums text-brand">+{{ $row['points'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-sm text-gray-500">{{ __('site.rewards.not_every') }}</p>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ __('site.rewards.catalog_title') }}</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($catalog as $reward)
                        <li class="rounded-2xl ring-1 ring-brand/15 bg-white px-4 py-4">
                            <p class="text-xs uppercase tracking-widest font-bold text-brand">{{ $reward['points'] }} pts</p>
                            <p class="mt-1 font-bold text-gray-900">{{ $reward['label'] }}</p>
                            @if ($reward['description'])
                                <p class="mt-1 text-sm text-gray-600">{{ $reward['description'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
</x-site.layout>
