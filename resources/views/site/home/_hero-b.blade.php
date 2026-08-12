{{-- Variant B: centered, product-first messaging --}}
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

    @include('site.home._lno-disclosure', ['centered' => true])

    @guest
        <p class="mt-4 text-sm text-gray-500">
            <a href="{{ route('site.login') }}" class="text-brand font-semibold hover:underline">{{ __('site.nav.log_in') }}</a>
        </p>
    @endguest

    <div class="mt-10 grid grid-cols-3 gap-3 sm:gap-4 max-w-lg mx-auto text-left">
        @foreach (__('site.hero.variant_b_pills') as $pill)
            <div class="glass-card px-3 py-3 text-center">
                <span class="text-xl block mb-1" aria-hidden="true">{{ $pill['icon'] }}</span>
                <span class="text-[11px] font-semibold text-gray-800 leading-tight">{{ $pill['label'] }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="hidden sm:flex justify-center mt-10 max-w-md mx-auto">
    <x-site.hero-illustration class="scale-90 opacity-95" />
</div>
