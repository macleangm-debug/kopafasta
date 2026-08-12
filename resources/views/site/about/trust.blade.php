<x-site.layout :title="brand_title(__('site.about.trust.title'))"
                :description="__('site.about.trust.meta')">

    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-10 lg:pt-20 lg:pb-14">
            <p class="text-xs uppercase tracking-widest text-brand-gold mb-3">{{ __('site.about.trust.eyebrow') }}</p>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight max-w-3xl">{{ __('site.about.trust.hero_title') }}</h1>
            <p class="mt-5 text-lg text-white/80 max-w-2xl leading-relaxed">{{ __('site.about.trust.hero_body') }}</p>
        </div>
    </section>

    @include('site.about._nav', ['active' => 'trust'])

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach (__('site.about.trust.pillars') as $pillar)
                <div class="rounded-3xl border border-slate-200 bg-white p-7 hover:border-brand hover:shadow-xl transition">
                    <div class="size-12 grid place-items-center rounded-2xl bg-brand-muted text-2xl mb-4">{{ $pillar['icon'] }}</div>
                    <h2 class="text-xl font-bold text-slate-900">{{ $pillar['title'] }}</h2>
                    <p class="mt-3 text-sm text-slate-600 leading-relaxed">{{ $pillar['body'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-14 rounded-3xl bg-slate-50 border border-slate-200 p-8 lg:p-10">
            <p class="text-xs uppercase tracking-widest text-brand mb-2">{{ __('site.about.trust.promise_eyebrow') }}</p>
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight max-w-2xl">{{ __('site.about.trust.promise_title') }}</h2>
            <p class="mt-4 text-slate-600 max-w-3xl leading-relaxed">{{ __('site.about.trust.promise_body') }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('site.legal') }}" class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition">{{ __('site.about.trust.cta_legal') }}</a>
                <a href="{{ route('site.support') }}" class="inline-flex bg-white ring-1 ring-slate-200 hover:ring-brand text-brand font-semibold px-5 py-2.5 rounded-xl text-sm transition">{{ __('site.about.trust.cta_support') }}</a>
            </div>
        </div>
    </section>
</x-site.layout>
