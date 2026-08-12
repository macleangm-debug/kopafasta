<x-site.layout :title="brand_title(__('site.about.title'))"
                :description="__('site.about.meta_description')">

    {{-- HERO --}}
    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="absolute inset-0 opacity-15" style="background-image: radial-gradient(circle at 12% 80%, #f5c842 0%, transparent 42%), radial-gradient(circle at 88% 20%, #0d5c4d 0%, transparent 45%);"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <p class="text-xs uppercase tracking-widest text-brand-gold mb-4">{{ __('site.about.eyebrow') }}</p>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-tight max-w-3xl">
                {{ __('site.about.hero_title') }}
                <span class="text-brand-gold">{{ __('site.about.hero_accent') }}</span>
            </h1>
            <p class="mt-6 text-lg text-white/80 max-w-2xl leading-relaxed">
                {{ __('site.about.hero_body') }}
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('site.products') }}" class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-semibold px-6 py-3 rounded-full shadow-lg transition">
                    {{ __('site.about.cta_products') }}
                    <svg class="w-5 h-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                </a>
                <a href="{{ route('site.partners') }}" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 backdrop-blur text-white font-semibold px-6 py-3 rounded-full border border-white/20 transition">
                    {{ __('site.about.cta_partners') }}
                </a>
            </div>
        </div>
    </section>

    @include('site.about._nav', ['active' => 'overview'])

    {{-- Story links --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ([
                ['founding', 'site.about.founding', '📖'],
                ['trust', 'site.about.trust', '🛡️'],
                ['impact', 'site.about.impact', '✨'],
                ['roadmap', 'site.about.roadmap', '🗺️'],
            ] as [$key, $route, $icon])
                <a href="{{ route($route) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 hover:border-brand hover:shadow-lg transition">
                    <span class="text-2xl">{{ $icon }}</span>
                    <p class="mt-3 font-bold text-slate-900 group-hover:text-brand transition">{{ __('site.about.nav.'.$key) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('site.about.nav.'.$key.'_hint') }}</p>
                </a>
            @endforeach
        </div>
    </section>

    {{-- MISSION --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
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

        <div class="mt-14 grid sm:grid-cols-3 gap-5">
            @foreach (__('site.about.pillars') as $pillar)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 hover:border-brand hover:shadow-lg transition">
                    <div class="size-11 grid place-items-center rounded-xl bg-brand-muted text-2xl mb-4">{{ $pillar['icon'] }}</div>
                    <h3 class="text-lg font-bold text-slate-900">{{ $pillar['title'] }}</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $pillar['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- EXPANSION --}}
    <section class="bg-slate-50 border-t border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <p class="text-xs uppercase tracking-widest text-brand mb-2">{{ __('site.about.expansion_eyebrow') }}</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 max-w-2xl">{{ __('site.about.expansion_title') }}</h2>
            <p class="mt-4 text-slate-600 max-w-2xl leading-relaxed">{{ __('site.about.expansion_body') }}</p>

            <div class="mt-10 grid md:grid-cols-3 gap-6">
                @foreach (__('site.about.expansion_regions') as $region)
                    <div class="rounded-3xl bg-white border border-slate-200 p-7 hover:border-brand hover:shadow-xl transition">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-brand">{{ $region['label'] }}</p>
                        <h3 class="mt-2 text-xl font-bold text-slate-900">{{ $region['title'] }}</h3>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $region['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- PROJECT PARTNERS --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-xs uppercase tracking-widest text-brand mb-2">{{ __('site.about.partners_eyebrow') }}</p>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 max-w-3xl">{{ __('site.about.partners_title') }}</h2>
        <p class="mt-4 text-slate-600 max-w-3xl leading-relaxed">{{ __('site.about.partners_body') }}</p>

        <div class="mt-12 grid lg:grid-cols-2 gap-8 items-stretch">
            <div class="rounded-3xl bg-brand text-white p-8 lg:p-10 relative overflow-hidden">
                <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
                <div class="relative">
                    <p class="text-xs uppercase tracking-widest text-brand-gold mb-3">{{ __('site.about.conduit_eyebrow') }}</p>
                    <h3 class="text-2xl font-bold tracking-tight">{{ __('site.about.conduit_title') }}</h3>
                    <p class="mt-4 text-white/80 leading-relaxed">{{ __('site.about.conduit_body') }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach (__('site.about.conduit_points') as $point)
                            <li class="flex gap-3 text-sm text-white/90">
                                <span class="mt-0.5 size-5 shrink-0 rounded-full bg-brand-gold text-brand grid place-items-center text-[10px] font-bold">✓</span>
                                <span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="space-y-5">
                @foreach (__('site.about.partner_examples') as $example)
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 hover:border-brand hover:shadow-lg transition">
                        <h3 class="text-lg font-bold text-slate-900">{{ $example['title'] }}</h3>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $example['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- WHO WE SERVE --}}
    <section class="premium-gradient border-y border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <p class="text-xs uppercase tracking-widest text-brand mb-2 text-center">{{ __('site.about.serve_eyebrow') }}</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 text-center">{{ __('site.about.serve_title') }}</h2>
            <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach (__('site.about.audiences') as $audience)
                    <div class="glass-card p-6 text-center hover:shadow-lg transition">
                        <div class="text-3xl mb-3">{{ $audience['icon'] }}</div>
                        <h3 class="font-bold text-slate-900">{{ $audience['title'] }}</h3>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $audience['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- BRAND DISCUSSION / NEXT --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="rounded-3xl border border-slate-200 bg-white p-8 lg:p-12">
            <p class="text-xs uppercase tracking-widest text-brand mb-2">{{ __('site.about.next_eyebrow') }}</p>
            <h2 class="text-3xl font-bold tracking-tight text-slate-900 max-w-2xl">{{ __('site.about.next_title') }}</h2>
            <p class="mt-4 text-slate-600 max-w-2xl leading-relaxed">{{ __('site.about.next_body') }}</p>
            <div class="mt-8 grid sm:grid-cols-2 gap-4">
                <a href="{{ route('site.about.founding') }}" class="rounded-xl bg-slate-50 ring-1 ring-slate-100 px-4 py-3 text-sm font-semibold text-brand hover:ring-brand transition">{{ __('site.about.nav.founding') }} →</a>
                <a href="{{ route('site.about.trust') }}" class="rounded-xl bg-slate-50 ring-1 ring-slate-100 px-4 py-3 text-sm font-semibold text-brand hover:ring-brand transition">{{ __('site.about.nav.trust') }} →</a>
                <a href="{{ route('site.about.impact') }}" class="rounded-xl bg-slate-50 ring-1 ring-slate-100 px-4 py-3 text-sm font-semibold text-brand hover:ring-brand transition">{{ __('site.about.nav.impact') }} →</a>
                <a href="{{ route('site.about.roadmap') }}" class="rounded-xl bg-slate-50 ring-1 ring-slate-100 px-4 py-3 text-sm font-semibold text-brand hover:ring-brand transition">{{ __('site.about.nav.roadmap') }} →</a>
            </div>
            <div class="mt-10 flex flex-wrap gap-3">
                <a href="mailto:{{ brand('support_email') }}" class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-xl transition shadow-md">
                    {{ __('site.about.cta_contact') }}
                </a>
                <a href="{{ route('site.partners') }}" class="inline-flex items-center gap-2 bg-white ring-1 ring-slate-200 hover:ring-brand text-brand font-semibold px-6 py-3 rounded-xl transition">
                    {{ __('site.about.cta_partners') }}
                </a>
            </div>
        </div>
    </section>
</x-site.layout>
