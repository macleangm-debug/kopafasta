{{-- Variant B: centered brand plane — no device frames --}}
<div class="relative overflow-hidden rounded-[1.75rem] sm:rounded-[2rem] bg-gradient-to-br from-brand via-[#0f6b54] to-[#082f27] text-white text-center shadow-[0_28px_70px_rgba(8,47,39,0.28)] ring-1 ring-brand-gold/20 px-6 sm:px-10 py-12 sm:py-16">
    <div class="absolute inset-0 opacity-[0.16] pointer-events-none" style="background-image:url(\"data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.55' stroke-width='2'/%3E%3C/svg%3E\"); background-size:72px 48px;"></div>
    <div class="relative animate-fade-up max-w-2xl mx-auto">
        <p class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.22em] text-brand-gold">
            <span class="text-lg tracking-[-0.18em] leading-none" aria-hidden="true">›››</span>
            {{ __('site.hero.badge') }}
        </p>
        <h1 class="mt-5 text-3xl sm:text-5xl lg:text-[3.4rem] font-black tracking-tight leading-[1.05]">
            {{ __('site.hero.variant_b_title') }}
        </h1>
        <p class="mt-4 text-base sm:text-lg text-white/80 leading-relaxed">
            {{ __('site.hero.variant_b_subtitle') }}
        </p>

        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ route('site.products') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/15 ring-1 ring-white/25 text-white font-semibold px-8 py-4 rounded-xl transition">
                {{ __('site.hero.variant_b_cta_products') }}
            </a>
            <a href="{{ route('site.register.borrower') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-extrabold px-8 py-4 rounded-xl transition">
                {{ __('site.hero.get_started') }}
            </a>
        </div>

        @guest
            <p class="mt-4 text-sm text-white/65">
                <a href="{{ route('site.login') }}" class="text-brand-gold font-semibold hover:underline">{{ __('site.nav.log_in') }}</a>
            </p>
        @endguest

        <ul class="mt-10 grid sm:grid-cols-2 gap-3 text-left max-w-xl mx-auto">
            @foreach (__('site.hero.variant_b_pills') as $pill)
                <li class="flex items-start gap-3 rounded-2xl bg-white/8 ring-1 ring-white/10 px-4 py-3">
                    <span class="mt-0.5 text-brand-gold font-bold" aria-hidden="true">›</span>
                    <span class="text-sm font-semibold text-white/95 leading-snug">{{ $pill['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
