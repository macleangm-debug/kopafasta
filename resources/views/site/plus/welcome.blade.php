<x-site.borrower-layout :title="brand_title(__('plus.welcome.title'))" active="dashboard">
    <section class="kf-premium-panel rounded-2xl p-6 sm:p-8 space-y-4 text-center">
        <p class="relative text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ __('plus.welcome.kicker') }} ✦</p>
        <h1 class="relative text-2xl sm:text-3xl font-extrabold tracking-tight">{{ __('plus.welcome.title') }}</h1>
        <p class="relative text-sm text-white/85 max-w-md mx-auto">{{ __('plus.welcome.body') }}</p>
        <a href="{{ route('site.borrower.plus.home') }}" class="relative inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand px-6 py-3 font-bold shadow-sm ring-1 ring-brand-gold/40">{{ __('plus.welcome.open') }}</a>
    </section>
</x-site.borrower-layout>
