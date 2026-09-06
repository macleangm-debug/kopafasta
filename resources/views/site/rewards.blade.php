<x-site.layout :title="brand_name().' — '.__('site.rewards.title')">
    <x-site.public-hero
        variant="feature"
        :eyebrow="__('site.rewards.title')"
        :title="__('site.rewards.kicker')"
        :body="__('site.rewards.intro')"
        :primary-href="route('site.register.borrower')"
        :primary-label="__('site.rewards.cta')"
    >
        @if (! empty($catalog[0]))
            <p class="rounded-2xl bg-white/8 ring-1 ring-white/10 px-4 py-3.5 text-sm font-semibold text-white/95">
                {{ __('site.rewards.example', ['points' => number_format($catalog[0]['points']), 'reward' => $catalog[0]['label']]) }}
            </p>
        @endif
    </x-site.public-hero>

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
