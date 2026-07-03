<x-site.layout :title="brand_title(__('site.partners.title'))">
    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
            <p class="text-xs uppercase tracking-widest text-brand-gold mb-2">{{ __('site.partners.title') }}</p>
            <h1 class="text-3xl sm:text-4xl font-bold tracking-tight max-w-2xl">{{ __('site.partners.hero_title') }}</h1>
            <p class="mt-4 text-white/80 max-w-xl leading-relaxed">{{ __('site.partners.hero_body') }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('site.register.vendor') }}" class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-semibold px-6 py-3 rounded-xl transition">
                    {{ __('site.partners.cta_register') }}
                </a>
                <a href="{{ route('site.login', ['portal' => 'partner']) }}" class="inline-flex items-center gap-2 glass-card-dark font-semibold px-6 py-3 rounded-xl transition hover:bg-white/10">
                    {{ __('site.partners.cta_login') }}
                </a>
            </div>
            <p class="mt-4 text-sm text-white/60 max-w-lg">{{ __('site.partners.login_hint') }}</p>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-center mb-10">{{ __('site.auth.partner_portal') }}</h2>
            <div class="grid sm:grid-cols-3 gap-5 mb-12">
                @foreach ([
                    ['🤝', __('site.nav.affiliate'), __('site.affiliate.hero_body')],
                    ['🔧', __('site.partners.title'), __('site.partners.subtitle')],
                    ['🏢', __('site.footer.become_partner'), __('site.partners.hero_body')],
                ] as [$icon, $title, $body])
                    <div class="glass-card p-6 text-center">
                        <div class="text-4xl mb-3">{{ $icon }}</div>
                        <h3 class="font-bold text-gray-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-gray-600 line-clamp-3">{{ $body }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach ([
                    ['📡', 'GPS installers', 'Schedule installs from your phone. Same-day payout on completion.'],
                    ['🛡️', 'Insurance providers', 'Quote and bind cover for collateralised loans.'],
                    ['📋', 'Valuers', 'Inspect and value assets via our app with photo evidence.'],
                    ['🏭', 'Yard & collections', 'Help recover and remarket repossessed assets.'],
                ] as [$icon, $title, $body])
                    <div class="glass-card p-6 hover:shadow-lg transition">
                        <div class="size-12 grid place-items-center rounded-2xl bg-brand-muted text-2xl mb-3">{{ $icon }}</div>
                        <h3 class="font-bold text-gray-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $body }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-12 glass-card p-8 text-center max-w-2xl mx-auto">
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
