{{-- Variant B: centered, product-first messaging — no device frames --}}
<div class="max-w-3xl mx-auto text-center animate-fade-up">
    <span class="inline-flex items-center gap-2 rounded-full glass-card px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-gray-600">
        {{ __('site.hero.badge') }}
    </span>
    <h1 class="mt-5 text-3xl sm:text-5xl lg:text-[3.5rem] font-bold tracking-tight leading-[1.08] text-brand">
        {{ __('site.hero.variant_b_title') }}
    </h1>
    <p class="mt-4 text-base sm:text-lg text-gray-600 max-w-2xl mx-auto leading-relaxed">
        {{ __('site.hero.variant_b_subtitle') }}
    </p>

    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="{{ route('site.products') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-8 py-4 rounded-xl shadow-md transition">
            {{ __('site.hero.variant_b_cta_products') }}
        </a>
        <a href="{{ route('site.register.borrower') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-8 py-4 rounded-xl transition">
            {{ __('site.hero.get_started') }}
        </a>
    </div>

    @guest
        <p class="mt-4 text-sm text-gray-500">
            <a href="{{ route('site.login') }}" class="text-brand font-semibold hover:underline">{{ __('site.nav.log_in') }}</a>
        </p>
    @endguest

    <div class="mt-10 max-w-lg mx-auto rounded-3xl bg-gradient-to-br from-brand via-[#127A5F] to-[#082f27] p-8 text-left text-white shadow-[0_20px_48px_rgba(8,47,39,0.22)] ring-1 ring-brand-gold/20">
        <div class="flex items-center gap-3 text-brand-gold" aria-hidden="true">
            <span class="text-3xl font-black tracking-[-0.18em] leading-none">›››</span>
            <span class="text-xl font-bold tracking-tight text-white">{{ brand_name() }}</span>
        </div>
        <ul class="mt-6 space-y-3">
            @foreach (__('site.hero.variant_b_pills') as $pill)
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 text-brand-gold font-bold" aria-hidden="true">›</span>
                    <span class="text-sm font-semibold text-white/95 leading-snug">{{ $pill['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
