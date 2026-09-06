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

    <section id="how" class="py-10 lg:py-14 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-left max-w-2xl mb-8">
                <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.invest.how_kicker') }}</p>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __('site.invest.how_title') }}</h2>
                <p class="mt-2 text-gray-600">{{ __('site.invest.how_body') }}</p>
            </div>
            <ol class="grid md:grid-cols-3 gap-4">
                @foreach (__('site.invest.how_steps') as $i => $step)
                    <li class="rounded-2xl bg-[#f7faf8] ring-1 ring-brand/10 p-5 text-left">
                        <span class="inline-flex size-8 items-center justify-center rounded-full bg-brand text-white text-sm font-bold">{{ $i + 1 }}</span>
                        <h3 class="mt-3 font-bold text-gray-900">{{ $step['title'] }}</h3>
                        <p class="mt-1.5 text-sm text-gray-600 leading-relaxed">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="py-10 lg:py-14 bg-[#f7faf8] border-y border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-8">
            <div class="text-left">
                <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.invest.structure_kicker') }}</p>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __('site.invest.structure_title') }}</h2>
                <p class="mt-2 text-gray-600">{{ __('site.invest.structure_body') }}</p>
                <ul class="mt-5 space-y-3">
                    @foreach (__('site.invest.structure_points') as $item)
                        <li class="flex gap-2 text-sm text-gray-700"><span class="text-brand font-bold">›</span><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-6 text-left shadow-sm">
                <h3 class="font-bold text-gray-900">{{ __('site.invest.safeguards_title') }}</h3>
                <ul class="mt-4 space-y-3 text-sm text-gray-700">
                    @foreach (__('site.invest.safeguards') as $item)
                        <li class="flex gap-2"><span class="text-brand-gold font-bold">›</span><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="py-10 lg:py-14 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-left">
            <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.invest.journey_kicker') }}</p>
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __('site.invest.journey_title') }}</h2>
            <p class="mt-2 text-gray-600">{{ __('site.invest.journey_body') }}</p>
            <a href="{{ route('site.register.capital') }}" class="mt-6 inline-flex rounded-xl bg-brand hover:bg-brand-light text-white font-extrabold px-6 py-3.5 shadow-md">
                {{ __('site.invest.cta_apply') }}
            </a>
            <p class="mt-4 text-xs text-gray-500 leading-relaxed">{{ __('site.invest.disclosure') }}</p>
        </div>
    </section>
</x-site.layout>
