{{-- Variant A: full-bleed brand composition — typography + chevrons, no device mockups --}}
<div class="relative overflow-hidden rounded-[1.75rem] sm:rounded-[2rem] bg-gradient-to-br from-brand via-[#0f6b54] to-[#082f27] text-white shadow-[0_28px_70px_rgba(8,47,39,0.28)] ring-1 ring-brand-gold/20">
    <div class="absolute inset-0 opacity-[0.18] pointer-events-none" style="background-image:url(\"data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.55' stroke-width='2'/%3E%3C/svg%3E\"); background-size:72px 48px;"></div>
    <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-brand-gold/15 pointer-events-none"></div>
    <div class="absolute -left-20 bottom-0 h-52 w-52 rounded-full bg-white/5 pointer-events-none"></div>

    <div class="relative grid lg:grid-cols-2 gap-8 lg:gap-12 items-center px-6 sm:px-10 lg:px-14 py-10 sm:py-14 lg:py-16">
        <div class="animate-fade-up">
            <p class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.22em] text-brand-gold">
                <span class="text-lg tracking-[-0.18em] leading-none" aria-hidden="true">›››</span>
                {{ __('site.hero.badge') }}
            </p>
            <h1 class="mt-4 text-3xl sm:text-5xl lg:text-[3.35rem] font-black tracking-tight leading-[1.05]">
                {{ __('site.hero.title') }}
            </h1>
            <p class="mt-4 text-base sm:text-lg text-white/80 max-w-lg leading-relaxed">
                {{ __('site.hero.subtitle') }}
            </p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('site.register.borrower') }}" class="inline-flex items-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-extrabold px-6 py-3.5 rounded-xl shadow-md transition">
                    {{ __('site.hero.get_started') }}
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                </a>
                <a href="{{ route('site.how-it-works') }}" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/15 ring-1 ring-white/25 text-white font-semibold px-6 py-3.5 rounded-xl transition">
                    {{ __('site.hero.learn_more') }}
                </a>
            </div>
            @guest
                <p class="mt-5 text-sm text-white/65">
                    {{ __('site.hero.login_cta') }}
                    <a href="{{ route('site.login') }}" class="text-brand-gold font-semibold hover:underline">{{ __('site.nav.log_in') }}</a>
                </p>
            @endguest
        </div>

        <div class="hidden lg:block animate-fade-up">
            <ul class="space-y-4">
                @foreach ([
                    __('site.hero.showcase_home'),
                    __('site.hero.showcase_loans'),
                    __('site.hero.showcase_market'),
                    __('site.hero.showcase_plus'),
                ] as $line)
                    <li class="flex items-start gap-3 rounded-2xl bg-white/8 ring-1 ring-white/10 px-4 py-3.5 backdrop-blur-[2px]">
                        <span class="mt-0.5 text-brand-gold font-black tracking-[-0.14em]" aria-hidden="true">›››</span>
                        <span class="text-base font-semibold text-white/95 leading-snug">{{ $line }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
