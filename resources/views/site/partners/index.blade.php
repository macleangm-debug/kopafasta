<x-site.layout :title="brand_title(__('site.partners.title'))">
    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
            <p class="text-xs uppercase tracking-widest text-brand-gold mb-2">{{ __('site.partners.title') }}</p>
            <h1 class="text-3xl sm:text-4xl font-bold tracking-tight max-w-2xl">{{ __('site.partners.hero_title') }}</h1>
            <p class="mt-4 text-white/80 max-w-xl leading-relaxed">{{ __('site.partners.hero_body') }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('site.partners.apply', 'debt_collector') }}" class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-semibold px-6 py-3 rounded-xl transition">
                    {{ __('site.partners.cta_enroll') }}
                </a>
                <a href="{{ route('site.login', ['portal' => 'partner']) }}" class="inline-flex items-center gap-2 glass-card-dark font-semibold px-6 py-3 rounded-xl transition hover:bg-white/10">
                    {{ __('site.partners.cta_login') }}
                </a>
            </div>
            <p class="mt-4 text-sm text-white/60 max-w-lg">{{ __('site.partners.login_hint') }}</p>
        </div>
    </section>

    @if (session('status'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        </div>
    @endif

    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-center mb-3">{{ __('site.partners.enroll_title') }}</h2>
            <p class="text-center text-sm text-gray-600 max-w-2xl mx-auto mb-10">{{ __('site.partners.enroll_body') }}</p>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-12">
                @foreach ([
                    ['debt_collector', '💼', __('site.partner_apply.types.debt_collector'), __('site.partners.card_collection')],
                    ['valuer', '📋', __('site.partner_apply.types.valuer'), __('site.partners.card_valuer')],
                    ['gps_installer', '📡', __('site.partner_apply.types.gps_installer'), __('site.partners.card_gps')],
                    ['insurance', '🛡️', __('site.partner_apply.types.insurance'), __('site.partners.card_insurance')],
                    ['yard', '🏭', __('site.partner_apply.types.yard'), __('site.partners.card_yard')],
                    ['auctioneer', '🔨', __('site.partner_apply.types.auctioneer'), __('site.partners.card_auctioneer')],
                    ['legal_partner', '⚖️', __('site.partner_apply.types.legal_partner'), __('site.partners.card_legal')],
                    ['call_center', '🎧', __('site.partner_apply.types.call_center'), __('site.partners.card_call_center')],
                ] as [$slug, $icon, $title, $body])
                    <a href="{{ route('site.partners.apply', $slug) }}" class="glass-card p-6 hover:shadow-lg transition block group">
                        <div class="size-12 grid place-items-center rounded-2xl bg-brand-muted text-2xl mb-3">{{ $icon }}</div>
                        <h3 class="font-bold text-gray-900 group-hover:text-brand transition">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $body }}</p>
                        <span class="mt-4 inline-flex text-sm font-semibold text-brand">{{ __('site.partners.apply_now') }} →</span>
                    </a>
                @endforeach
            </div>

            <div class="grid sm:grid-cols-2 gap-5 mb-12">
                <a href="{{ route('site.affiliate.apply') }}" class="glass-card p-6 hover:shadow-lg transition block">
                    <h3 class="font-bold text-gray-900">{{ __('site.nav.affiliate') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('site.partners.card_affiliate') }}</p>
                    <span class="mt-4 inline-flex text-sm font-semibold text-brand">{{ __('site.partners.apply_now') }} →</span>
                </a>
                <a href="{{ route('site.register.capital') }}" class="glass-card p-6 hover:shadow-lg transition block">
                    <h3 class="font-bold text-gray-900">{{ __('site.footer.capital_partner') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('site.partners.card_capital') }}</p>
                    <span class="mt-4 inline-flex text-sm font-semibold text-brand">{{ __('site.partners.apply_now') }} →</span>
                </a>
            </div>

            <div class="glass-card p-8 text-center max-w-2xl mx-auto">
                <h3 class="font-bold text-lg">{{ __('site.auth.activate_account') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ __('site.partners.login_hint') }}</p>
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('site.partner.start') }}" class="inline-flex bg-brand text-white font-semibold px-5 py-2.5 rounded-xl text-sm hover:bg-brand-light transition">{{ __('site.auth.activate_account') }}</a>
                    <a href="{{ route('site.login', ['portal' => 'partner']) }}" class="inline-flex ring-1 ring-brand/30 text-brand font-semibold px-5 py-2.5 rounded-xl text-sm hover:bg-brand-muted transition">{{ __('site.partners.cta_login') }}</a>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
