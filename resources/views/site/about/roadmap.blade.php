<x-site.layout :title="brand_title(__('site.about.roadmap.title'))"
                :description="__('site.about.roadmap.meta')">

    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_left,_#f5c842,_transparent_50%)]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-10 lg:pt-20 lg:pb-14">
            <p class="text-xs uppercase tracking-widest text-brand-gold mb-3">{{ __('site.about.roadmap.eyebrow') }}</p>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight max-w-3xl">{{ __('site.about.roadmap.hero_title') }}</h1>
            <p class="mt-5 text-lg text-white/80 max-w-2xl leading-relaxed">{{ __('site.about.roadmap.hero_body') }}</p>
        </div>
    </section>

    @include('site.about._nav', ['active' => 'roadmap'])

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
        <div class="relative">
            <div class="hidden lg:block absolute left-0 right-0 top-16 h-px bg-brand/20" aria-hidden="true"></div>
            <ol class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach (__('site.about.roadmap.countries') as $i => $country)
                    <li class="relative rounded-3xl border bg-white p-6 transition
                               {{ ! empty($country['live']) ? 'border-brand shadow-lg shadow-brand/10' : 'border-slate-200 hover:border-brand hover:shadow-lg' }}">
                        <div class="flex items-start justify-between gap-3">
                            <span class="size-10 rounded-full grid place-items-center text-sm font-bold
                                         {{ ! empty($country['live']) ? 'bg-brand text-brand-gold' : 'bg-slate-100 text-slate-600' }}">
                                {{ $i + 1 }}
                            </span>
                            @if (! empty($country['live']))
                                <span class="text-[10px] uppercase tracking-wider font-bold text-emerald-700 bg-emerald-50 ring-1 ring-emerald-200 px-2 py-1 rounded-lg">{{ __('site.about.roadmap.live') }}</span>
                            @else
                                <span class="text-[10px] uppercase tracking-wider font-bold text-amber-800 bg-amber-50 ring-1 ring-amber-200 px-2 py-1 rounded-lg">{{ __('site.about.roadmap.planned') }}</span>
                            @endif
                        </div>
                        <h2 class="mt-4 text-xl font-bold text-slate-900">{{ $country['name'] }}</h2>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $country['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="mt-14 rounded-3xl bg-brand text-white p-8 lg:p-10 relative overflow-hidden">
            <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)]"></div>
            <div class="relative max-w-2xl">
                <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">{{ __('site.about.roadmap.cta_title') }}</h2>
                <p class="mt-3 text-white/80 leading-relaxed">{{ __('site.about.roadmap.cta_body') }}</p>
                <a href="mailto:{{ brand('support_email') }}" class="mt-6 inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-semibold px-6 py-3 rounded-xl transition">
                    {{ __('site.about.roadmap.cta_button') }}
                </a>
            </div>
        </div>
    </section>
</x-site.layout>
