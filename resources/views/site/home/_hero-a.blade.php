{{-- Variant A: split layout with side illustration --}}
<div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
    <div class="animate-fade-up">
        <span class="inline-flex items-center gap-2 rounded-full glass-card px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-gray-600">
            {{ __('site.hero.badge') }}
        </span>
        <h1 class="mt-4 text-3xl sm:text-5xl lg:text-[3.25rem] font-bold tracking-tight leading-[1.1] text-brand">
            {{ __('site.hero.title') }}
        </h1>
        <p class="mt-4 text-base sm:text-lg text-gray-600 max-w-lg leading-relaxed">
            {{ __('site.hero.subtitle') }}
        </p>
        <div class="mt-7 flex flex-wrap gap-3">
            <a href="{{ route('site.register.borrower') }}" class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3.5 rounded-xl shadow-md transition hover:shadow-lg">
                {{ __('site.hero.get_started') }}
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
            </a>
            <a href="{{ route('site.how-it-works') }}" class="inline-flex items-center gap-2 glass-card hover:bg-white text-brand font-semibold px-6 py-3.5 rounded-xl transition">
                {{ __('site.hero.learn_more') }}
            </a>
        </div>
        @include('site.home._lno-disclosure')
        @guest
            <p class="mt-5 text-sm text-gray-500">
                {{ __('site.hero.login_cta') }}
                <a href="{{ route('site.login') }}" class="text-brand font-semibold hover:underline">{{ __('site.nav.log_in') }}</a>
            </p>
        @endguest
    </div>
    <div class="hidden lg:block animate-fade-up">
        @include('site.home._product-showcase')
    </div>
</div>
