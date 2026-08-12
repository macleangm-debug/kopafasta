<x-site.layout :title="brand_title(__('site.about.impact.title'))"
                :description="__('site.about.impact.meta')">

    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-10 lg:pt-20 lg:pb-14">
            <p class="text-xs uppercase tracking-widest text-brand-gold mb-3">{{ __('site.about.impact.eyebrow') }}</p>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight max-w-3xl">{{ __('site.about.impact.hero_title') }}</h1>
            <p class="mt-5 text-lg text-white/80 max-w-2xl leading-relaxed">{{ __('site.about.impact.hero_body') }}</p>
        </div>
    </section>

    @include('site.about._nav', ['active' => 'impact'])

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach (__('site.about.impact.stats') as $stat)
                <div class="rounded-3xl bg-white border border-slate-200 p-6 text-center hover:border-brand hover:shadow-lg transition">
                    <p class="text-3xl sm:text-4xl font-bold text-brand tracking-tight">{{ $stat['value'] }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $stat['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-16 grid lg:grid-cols-2 gap-8">
            @foreach (__('site.about.impact.stories') as $story)
                <article class="rounded-3xl overflow-hidden border border-slate-200 bg-white hover:shadow-xl transition">
                    <div class="h-2 bg-gradient-to-r from-brand to-brand-gold"></div>
                    <div class="p-7">
                        <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ $story['tag'] }}</p>
                        <h2 class="mt-2 text-xl font-bold text-slate-900">{{ $story['title'] }}</h2>
                        <p class="mt-3 text-sm text-slate-600 leading-relaxed">{{ $story['body'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>

        <p class="mt-12 text-center text-sm text-slate-500 max-w-2xl mx-auto">{{ __('site.about.impact.footnote') }}</p>
    </section>
</x-site.layout>
