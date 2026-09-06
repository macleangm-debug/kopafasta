<x-site.layout
    :title="brand_title(__('site.invest.meta_title'))"
    :description="__('site.invest.meta_desc')"
    :seo="['indexable' => false]"
>
    <x-site.public-hero
        variant="feature"
        :eyebrow="__('site.invest.eyebrow')"
        :title="__('site.invest.hero_title')"
        :body="__('site.invest.hero_body')"
        :primary-href="route('site.register.capital')"
        :primary-label="__('site.invest.cta_apply')"
        :secondary-href="'#how'"
        :secondary-label="__('site.invest.cta_how')"
    >
        <ul class="space-y-3">
            @foreach ([
                __('site.invest.point_1'),
                __('site.invest.point_2'),
                __('site.invest.point_3'),
                __('site.invest.point_4'),
            ] as $point)
                <li class="flex items-start gap-3 rounded-2xl bg-white/8 ring-1 ring-white/10 px-4 py-3.5">
                    <span class="mt-0.5 text-brand-gold font-black tracking-[-0.14em]" aria-hidden="true">›››</span>
                    <span class="text-sm font-semibold text-white/95 leading-snug">{{ $point }}</span>
                </li>
            @endforeach
        </ul>
    </x-site.public-hero>

    <x-site.public-section id="how">
        <x-site.public-carousel :title="__('site.invest.how_title')" :subtitle="__('site.invest.how_body')">
            @foreach (__('site.invest.how_steps') as $i => $step)
                <div data-public-slide class="snap-start shrink-0 w-[82%] sm:w-[45%] lg:w-[31%]">
                    <x-site.public-card :eyebrow="($i + 1)" :title="$step['title']">{{ $step['body'] }}</x-site.public-card>
                </div>
            @endforeach
        </x-site.public-carousel>
    </x-site.public-section>

    <x-site.public-section tone="muted">
        <div class="grid lg:grid-cols-2 gap-6">
            <x-site.public-card :eyebrow="__('site.invest.structure_kicker')" :title="__('site.invest.structure_title')">
                <p>{{ __('site.invest.structure_body') }}</p>
                <ul class="mt-4 space-y-2">
                    @foreach (__('site.invest.structure_points') as $item)
                        <li class="flex gap-2"><span class="text-brand-gold font-black tracking-[-0.14em]">›››</span><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </x-site.public-card>
            <x-site.public-card :title="__('site.invest.safeguards_title')">
                <ul class="space-y-2">
                    @foreach (__('site.invest.safeguards') as $item)
                        <li class="flex gap-2"><span class="text-brand-gold font-black tracking-[-0.14em]">›››</span><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </x-site.public-card>
        </div>
    </x-site.public-section>

    <x-site.public-section>
        <x-site.public-cta-band
            :title="__('site.invest.journey_title')"
            :body="__('site.invest.journey_body')"
            :primary-href="route('site.register.capital')"
            :primary-label="__('site.invest.cta_apply')"
        />
        <p class="mt-4 text-xs text-gray-500 max-w-2xl">{{ __('site.invest.disclosure') }}</p>
    </x-site.public-section>
</x-site.layout>
