<x-site.layout :title="brand_title(__('site.about.title'))"
                :description="__('site.about.meta_description')">

    <x-site.public-hero
        variant="feature"
        :eyebrow="__('site.about.eyebrow')"
        :title="__('site.about.hero_title').' '.__('site.about.hero_accent')"
        :body="__('site.about.hero_body')"
        :primary-href="route('site.about.founding')"
        :primary-label="__('site.about.story_cta')"
        :secondary-href="route('site.products')"
        :secondary-label="__('site.about.cta_products')"
    />

    @include('site.about._nav', ['active' => 'overview'])

    <x-site.public-section tone="muted" class="!py-10">
        <x-site.public-carousel :title="null">
            @foreach ([
                ['founding', 'site.about.founding'],
                ['trust', 'site.about.trust'],
                ['impact', 'site.about.impact'],
                ['roadmap', 'site.about.roadmap'],
            ] as [$key, $route])
                <a href="{{ route($route) }}" data-public-slide
                   class="snap-start shrink-0 w-[78%] sm:w-[42%] lg:w-[23%] rounded-2xl ring-1 ring-brand/10 bg-white p-5 hover:ring-brand/30 transition">
                    <span class="text-brand-gold font-black tracking-[-0.14em]" aria-hidden="true">›››</span>
                    <p class="mt-3 font-bold text-gray-900">{{ __('site.about.nav.'.$key) }}</p>
                    <p class="mt-1 text-xs text-gray-500 leading-relaxed">{{ __('site.about.nav.'.$key.'_hint') }}</p>
                </a>
            @endforeach
        </x-site.public-carousel>
    </x-site.public-section>

    <x-site.public-section>
        <div class="grid lg:grid-cols-12 gap-10 items-start">
            <div class="lg:col-span-5">
                <p class="text-xs uppercase tracking-widest text-brand mb-2">{{ __('site.about.mission_eyebrow') }}</p>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">{{ __('site.about.mission_title') }}</h2>
            </div>
            <div class="lg:col-span-7 space-y-5 text-slate-600 leading-relaxed">
                <p class="text-lg text-slate-700">{{ __('site.about.mission_lead') }}</p>
                <p>{{ __('site.about.mission_body') }}</p>
            </div>
        </div>

        <div class="mt-12 grid sm:grid-cols-3 gap-5">
            @foreach (__('site.about.pillars') as $pillar)
                <x-site.public-card :title="$pillar['title']">{{ $pillar['body'] }}</x-site.public-card>
            @endforeach
        </div>
    </x-site.public-section>

    <x-site.public-section tone="muted">
        <p class="text-xs uppercase tracking-widest text-brand mb-2">{{ __('site.about.expansion_eyebrow') }}</p>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 max-w-2xl">{{ __('site.about.expansion_title') }}</h2>
        <p class="mt-4 text-slate-600 max-w-2xl leading-relaxed">{{ __('site.about.expansion_body') }}</p>
        <div class="mt-10 grid md:grid-cols-3 gap-5">
            @foreach (__('site.about.expansion_regions') as $region)
                <x-site.public-card :eyebrow="$region['label']" :title="$region['title']">{{ $region['body'] }}</x-site.public-card>
            @endforeach
        </div>
    </x-site.public-section>

    <x-site.public-section>
        <x-site.public-cta-band
            :title="__('site.about.next_title')"
            :body="__('site.about.next_body')"
            :primary-href="route('site.about.founding')"
            :primary-label="__('site.about.story_cta')"
            :secondary-href="route('site.support')"
            :secondary-label="__('site.about.cta_contact')"
        />
    </x-site.public-section>
</x-site.layout>
