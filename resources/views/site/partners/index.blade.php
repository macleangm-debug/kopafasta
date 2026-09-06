<x-site.layout :title="brand_title(__('site.partners.title'))">
    <x-site.public-hero
        variant="feature"
        :eyebrow="__('site.partners.title')"
        :title="__('site.partners.hero_title')"
        :body="__('site.partners.hero_body')"
        :primary-href="route('site.partners.apply', 'debt_collector')"
        :primary-label="__('site.partners.cta_enroll')"
        :secondary-href="route('site.login.partner')"
        :secondary-label="__('site.partners.cta_login')"
    />

    @if (session('status'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        </div>
    @endif

    <x-site.public-section>
        <x-site.public-carousel :title="__('site.partners.why_title')" :subtitle="__('site.partners.why_body')">
            @foreach ([
                [__('site.partners.why_jobs_title'), __('site.partners.why_jobs_body')],
                [__('site.partners.why_pay_title'), __('site.partners.why_pay_body')],
                [__('site.partners.why_tools_title'), __('site.partners.why_tools_body')],
            ] as [$title, $body])
                <div data-public-slide class="snap-start shrink-0 w-[min(100%,calc(100vw-3rem))] sm:w-[280px] lg:w-[calc(33.333%-11px)]">
                    <x-site.public-card :title="$title">{{ $body }}</x-site.public-card>
                </div>
            @endforeach
        </x-site.public-carousel>
    </x-site.public-section>

    <section class="py-14 bg-[#faf8f5]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-center mb-3">{{ __('site.partners.enroll_title') }}</h2>
            <p class="text-center text-sm text-gray-600 max-w-2xl mx-auto mb-10">{{ __('site.partners.enroll_body') }}</p>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-12">
                @foreach ([
                    ['debt_collector', __('site.partner_apply.types.debt_collector'), __('site.partners.card_collection')],
                    ['valuer', __('site.partner_apply.types.valuer'), __('site.partners.card_valuer')],
                    ['gps_installer', __('site.partner_apply.types.gps_installer'), __('site.partners.card_gps')],
                    ['insurance', __('site.partner_apply.types.insurance'), __('site.partners.card_insurance')],
                    ['yard', __('site.partner_apply.types.yard'), __('site.partners.card_yard')],
                    ['auctioneer', __('site.partner_apply.types.auctioneer'), __('site.partners.card_auctioneer')],
                    ['legal_partner', __('site.partner_apply.types.legal_partner'), __('site.partners.card_legal')],
                    ['call_center', __('site.partner_apply.types.call_center'), __('site.partners.card_call_center')],
                ] as [$slug, $title, $body])
                    <a href="{{ route('site.partners.apply', $slug) }}" class="group relative overflow-hidden rounded-2xl bg-brand text-white p-6 shadow-lg hover:shadow-xl transition block ring-1 ring-brand/20">
                        <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
                        <div class="relative">
                            <span class="text-brand-gold font-black tracking-[-0.14em] text-2xl" aria-hidden="true">›››</span>
                            <h3 class="font-bold text-white group-hover:text-brand-gold transition">{{ $title }}</h3>
                            <p class="mt-2 text-sm text-white/75 leading-relaxed">{{ $body }}</p>
                            <span class="mt-4 inline-flex text-sm font-semibold text-brand-gold">{{ __('site.partners.apply_now') }} →</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="grid sm:grid-cols-2 gap-5 mb-12">
                <a href="{{ route('site.affiliate.apply') }}" class="glass-card p-6 hover:shadow-lg transition block">
                    <h3 class="font-bold text-gray-900">{{ __('site.nav.affiliate') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('site.partners.card_affiliate') }}</p>
                    <span class="mt-4 inline-flex text-sm font-semibold text-brand">{{ __('site.partners.apply_now') }} →</span>
                </a>
            </div>

            <div class="glass-card p-8 max-w-3xl mx-auto mb-12">
                <h3 class="font-bold text-lg text-center">{{ __('site.partners.how_title') }}</h3>
                <ol class="mt-6 space-y-4">
                    @foreach ([
                        __('site.partners.how_1'),
                        __('site.partners.how_2'),
                        __('site.partners.how_3'),
                        __('site.partners.how_4'),
                    ] as $i => $step)
                        <li class="flex gap-3 text-sm text-gray-700">
                            <span class="size-7 shrink-0 grid place-items-center rounded-full bg-brand text-white text-xs font-bold">{{ $i + 1 }}</span>
                            <span class="pt-1">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="grid lg:grid-cols-2 gap-6 mb-12">
                <div class="glass-card p-8">
                    <h3 class="font-bold text-lg">{{ __('site.partners.need_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('site.partners.need_body') }}</p>
                    <ul class="mt-5 space-y-3 text-sm text-gray-700">
                        @foreach ([
                            __('site.partners.need_1'),
                            __('site.partners.need_2'),
                            __('site.partners.need_3'),
                            __('site.partners.need_4'),
                        ] as $item)
                            <li class="flex gap-2">
                                <span class="text-brand font-bold">✓</span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="glass-card p-8">
                    <h3 class="font-bold text-lg">{{ __('site.partners.faq_title') }}</h3>
                    <dl class="mt-5 space-y-4">
                        @foreach ([
                            [__('site.partners.faq_1_q'), __('site.partners.faq_1_a')],
                            [__('site.partners.faq_2_q'), __('site.partners.faq_2_a')],
                            [__('site.partners.faq_3_q'), __('site.partners.faq_3_a')],
                        ] as [$q, $a])
                            <div>
                                <dt class="text-sm font-semibold text-gray-900">{{ $q }}</dt>
                                <dd class="mt-1 text-sm text-gray-600 leading-relaxed">{{ $a }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>

            <div class="glass-card p-8 text-center max-w-2xl mx-auto">
                <h3 class="font-bold text-lg">{{ __('site.auth.activate_account') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ __('site.partners.login_hint') }}</p>
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('site.partner.start') }}" class="inline-flex bg-brand text-white font-semibold px-5 py-2.5 rounded-xl text-sm hover:bg-brand-light transition">{{ __('site.auth.activate_account') }}</a>
                    <a href="{{ route('site.login.partner') }}" class="inline-flex ring-1 ring-brand/30 text-brand font-semibold px-5 py-2.5 rounded-xl text-sm hover:bg-brand-muted transition">{{ __('site.partners.cta_login') }}</a>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
