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

    <section class="py-10 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-left">
            <a href="{{ route('site.partners.apply.tracking') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.partner_apply.track_title') }} →</a>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-center mb-10">{{ __('site.affiliate.how_it_works') }}</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach ([
                    [1, __('site.affiliate.step_1'), __('site.affiliate.step_1_body')],
                    [2, __('site.affiliate.step_2'), __('site.affiliate.step_2_body')],
                    [3, __('site.affiliate.step_3'), __('site.affiliate.step_3_body')],
                    [4, __('site.affiliate.step_4'), __('site.affiliate.step_4_body')],
                ] as [$num, $title, $body])
                    <div class="glass-card p-6 relative overflow-hidden">
                        <span class="absolute -right-3 -top-3 text-6xl font-black text-brand/5">{{ $num }}</span>
                        <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('site.affiliate.step_label', ['num' => $num]) }}</p>
                        <h3 class="font-bold text-gray-900 mt-2">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-16 bg-[#faf8f5]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-8 items-center">
            <div class="glass-card p-8">
                <h2 class="text-xl font-bold text-gray-900">{{ __('site.affiliate.commission_title') }}</h2>
                <p class="mt-3 text-sm text-gray-600 leading-relaxed">{{ __('site.affiliate.commission_body') }}</p>
                <ul class="mt-6 space-y-3 text-sm">
                    @foreach ([__('site.affiliate.type_individual'), __('site.affiliate.type_company')] as $type)
                        <li class="flex items-center gap-2 text-gray-700">
                            <span class="size-6 rounded-full bg-brand-muted text-brand grid place-items-center text-xs">●</span>
                            {{ $type }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-xs text-gray-500">{{ __('site.affiliate.type_hint') }}</p>
            </div>
            <div class="glass-card p-8 bg-brand text-white relative overflow-hidden">
                <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
                <div class="relative">
                    <h2 class="text-xl font-bold">{{ __('site.affiliate.portal_title') }}</h2>
                    <p class="mt-3 text-sm text-white/80 leading-relaxed">{{ __('site.affiliate.portal_body') }}</p>
                    <p class="mt-4 text-sm text-white/70">{{ __('site.affiliate.after_approval') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('site.partners.apply.tracking') }}"
                           class="inline-flex bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('site.partner_apply.track_title') }}
                        </a>
                        <a href="{{ route('site.partner.start') }}"
                           class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('site.auth.activate_account') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-brand text-white text-center">
        <div class="max-w-2xl mx-auto px-4">
            <h2 class="text-2xl sm:text-3xl font-bold">{{ __('site.affiliate.cta_heading') }}</h2>
            <p class="mt-3 text-white/80">{{ __('site.affiliate.subtitle') }}</p>
            <a href="{{ route('site.affiliate.apply') }}"
               class="mt-8 inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-10 py-4 rounded-xl shadow-lg transition">
                {{ __('site.affiliate.cta_apply') }}
            </a>
        </div>
    </section>
</x-site.layout>
