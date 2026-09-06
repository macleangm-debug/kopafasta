<x-site.layout :title="brand_title(__('site.affiliate.title'))">
    <x-site.public-hero
        variant="feature"
        :eyebrow="__('site.affiliate.title')"
        :title="__('site.affiliate.hero_title')"
        :body="__('site.affiliate.hero_body')"
        :primary-href="route('site.affiliate.apply')"
        :primary-label="__('site.affiliate.cta_apply')"
        :secondary-href="route('site.login.partner')"
        :secondary-label="__('site.affiliate.portal_title')"
    >
        <ul class="space-y-3">
            @foreach ([
                __('site.affiliate.benefit_1'),
                __('site.affiliate.benefit_2'),
                __('site.affiliate.benefit_3'),
                __('site.affiliate.benefit_4'),
            ] as $benefit)
                <li class="flex items-start gap-3 rounded-2xl bg-white/8 ring-1 ring-white/10 px-4 py-3.5">
                    <span class="text-brand-gold font-black tracking-[-0.14em]" aria-hidden="true">›››</span>
                    <span class="text-sm font-semibold text-white/95">{{ $benefit }}</span>
                </li>
            @endforeach
        </ul>
    </x-site.public-hero>

    <x-site.public-section>
        <x-site.public-carousel :title="__('site.affiliate.how_it_works')">
            @foreach ([
                [1, __('site.affiliate.step_1'), __('site.affiliate.step_1_body')],
                [2, __('site.affiliate.step_2'), __('site.affiliate.step_2_body')],
                [3, __('site.affiliate.step_3'), __('site.affiliate.step_3_body')],
                [4, __('site.affiliate.step_4'), __('site.affiliate.step_4_body')],
            ] as [$num, $title, $body])
                <div data-public-slide class="snap-start shrink-0 w-[82%] sm:w-[45%] lg:w-[23%]">
                    <x-site.public-card :eyebrow="__('site.affiliate.step_label', ['num' => $num])" :title="$title">
                        {{ $body }}
                    </x-site.public-card>
                </div>
            @endforeach
        </x-site.public-carousel>
    </x-site.public-section>

    <x-site.public-section tone="muted">
        <div class="grid lg:grid-cols-2 gap-6 items-stretch">
            <x-site.public-card :title="__('site.affiliate.commission_title')">
                <p>{{ __('site.affiliate.commission_body') }}</p>
                <ul class="mt-4 space-y-2">
                    @foreach ([__('site.affiliate.type_individual'), __('site.affiliate.type_company')] as $type)
                        <li class="flex items-center gap-2 text-gray-700">
                            <span class="text-brand-gold font-black tracking-[-0.14em]">›››</span>
                            {{ $type }}
                        </li>
                    @endforeach
                </ul>
            </x-site.public-card>
            <div class="rounded-2xl bg-brand text-white p-6 sm:p-8 ring-1 ring-brand-gold/20">
                <h2 class="text-xl font-bold">{{ __('site.affiliate.portal_title') }}</h2>
                <p class="mt-3 text-sm text-white/80 leading-relaxed">{{ __('site.affiliate.portal_body') }}</p>
                <p class="mt-4 text-sm text-white/70">{{ __('site.affiliate.after_approval') }}</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('site.partners.apply.tracking') }}" class="inline-flex bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                        {{ __('site.partner_apply.track_title') }}
                    </a>
                    <a href="{{ route('site.partner.start') }}" class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                        {{ __('site.auth.activate_account') }}
                    </a>
                </div>
            </div>
        </div>
    </x-site.public-section>

    <x-site.public-section>
        <x-site.public-cta-band
            :title="__('site.affiliate.cta_heading')"
            :body="__('site.affiliate.subtitle')"
            :primary-href="route('site.affiliate.apply')"
            :primary-label="__('site.affiliate.cta_apply')"
        />
    </x-site.public-section>
</x-site.layout>
