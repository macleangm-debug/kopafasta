<x-site.layout :title="brand_title(__('site.about.founding.title'))"
                :description="__('site.about.founding.meta')">

    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-25" style="background-image: radial-gradient(circle at 10% 20%, #f5c842 0%, transparent 40%), radial-gradient(circle at 90% 80%, #0a4a3d 0%, transparent 45%);"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-10 lg:pt-20 lg:pb-14">
            <p class="text-xs uppercase tracking-widest text-brand-gold mb-3">{{ __('site.about.founding.eyebrow') }}</p>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight max-w-3xl leading-tight">{{ __('site.about.founding.hero_title') }}</h1>
            <p class="mt-5 text-lg text-white/80 max-w-2xl leading-relaxed">{{ __('site.about.founding.hero_body') }}</p>
        </div>
    </section>

    @include('site.about._nav', ['active' => 'founding'])

    {{-- Opening --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
        <div class="grid lg:grid-cols-12 gap-10 items-center">
            <div class="lg:col-span-7 space-y-5 text-slate-600 leading-relaxed">
                <p class="text-xl text-slate-800 font-medium">{{ __('site.about.founding.lead') }}</p>
                <p>{{ __('site.about.founding.body_1') }}</p>
                <p>{{ __('site.about.founding.body_2') }}</p>
            </div>
            <div class="lg:col-span-5">
                <div class="relative rounded-3xl bg-brand text-white p-8 overflow-hidden shadow-xl shadow-brand/20">
                    <div class="absolute -right-8 -top-8 size-40 rounded-full bg-brand-gold/20"></div>
                    <div class="absolute -left-6 bottom-0 size-28 rounded-full bg-white/10"></div>
                    <p class="relative text-xs uppercase tracking-widest text-brand-gold">{{ __('site.about.founding.stat_eyebrow') }}</p>
                    <p class="relative mt-3 text-5xl font-bold tracking-tight">100,000+</p>
                    <p class="relative mt-2 text-white/80">{{ __('site.about.founding.stat_label') }}</p>
                    <p class="relative mt-6 text-sm text-white/70 leading-relaxed">{{ __('site.about.founding.stat_hint') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Timeline --}}
    <section class="bg-slate-50 border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
            <p class="text-xs uppercase tracking-widest text-brand mb-2">{{ __('site.about.founding.timeline_eyebrow') }}</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">{{ __('site.about.founding.timeline_title') }}</h2>

            <ol class="mt-12 relative space-y-0">
                <div class="absolute left-[1.15rem] top-3 bottom-3 w-px bg-brand/20 hidden sm:block" aria-hidden="true"></div>
                @foreach (__('site.about.founding.timeline') as $i => $step)
                    <li class="relative sm:grid sm:grid-cols-12 sm:gap-8 py-6 sm:py-8">
                        <div class="sm:col-span-3 flex items-start gap-3">
                            <span class="relative z-10 size-9 shrink-0 rounded-full bg-brand text-brand-gold grid place-items-center text-sm font-bold ring-4 ring-slate-50">{{ $i + 1 }}</span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-brand">{{ $step['year'] }}</p>
                                <h3 class="mt-1 font-bold text-slate-900">{{ $step['title'] }}</h3>
                            </div>
                        </div>
                        <div class="sm:col-span-9 mt-3 sm:mt-0 pl-12 sm:pl-0">
                            <div class="rounded-2xl bg-white border border-slate-200 p-5 sm:p-6 hover:border-brand hover:shadow-lg transition">
                                <p class="text-sm text-slate-600 leading-relaxed">{{ $step['body'] }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Name origin --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
        <div class="rounded-[2rem] overflow-hidden ring-1 ring-brand/15 bg-white shadow-lg shadow-brand/5">
            <div class="grid lg:grid-cols-2">
                <div class="bg-gradient-to-br from-brand via-brand to-brand-light text-white p-8 lg:p-12 relative overflow-hidden">
                    <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 80% 20%, #f5c842 0%, transparent 45%);"></div>
                    <div class="relative">
                        <p class="text-xs uppercase tracking-widest text-brand-gold">{{ __('site.about.founding.name_eyebrow') }}</p>
                        <h2 class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight">{{ __('site.about.founding.name_title') }}</h2>
                        <p class="mt-4 text-white/80 leading-relaxed">{{ __('site.about.founding.name_body') }}</p>
                        <div class="mt-8 inline-flex items-center gap-3 rounded-2xl bg-white/10 ring-1 ring-white/20 px-5 py-4">
                            <span class="text-3xl font-bold text-brand-gold tracking-tight">kopafasta</span>
                        </div>
                        <p class="mt-3 text-sm text-white/70">{{ __('site.about.founding.name_meaning') }}</p>
                    </div>
                </div>
                <div class="p-8 lg:p-12 space-y-5">
                    <p class="text-xs uppercase tracking-widest text-brand">{{ __('site.about.founding.room_eyebrow') }}</p>
                    <h3 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('site.about.founding.room_title') }}</h3>
                    <p class="text-slate-600 leading-relaxed">{{ __('site.about.founding.room_body') }}</p>
                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-5">
                        <p class="text-sm font-semibold text-slate-900">{{ __('site.about.founding.room_place') }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ __('site.about.founding.room_place_hint') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Partners of the era --}}
    <section class="premium-gradient border-y border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid md:grid-cols-3 gap-5">
                @foreach (__('site.about.founding.highlights') as $card)
                    <div class="glass-card p-6 hover:shadow-lg transition">
                        <div class="text-3xl mb-3">{{ $card['icon'] }}</div>
                        <h3 class="font-bold text-slate-900">{{ $card['title'] }}</h3>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $card['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <p class="text-slate-600 max-w-2xl mx-auto">{{ __('site.about.founding.closing') }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('site.about.impact') }}" class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-xl transition">{{ __('site.about.nav.impact') }} →</a>
            <a href="{{ route('site.about.roadmap') }}" class="inline-flex bg-white ring-1 ring-slate-200 hover:ring-brand text-brand font-semibold px-6 py-3 rounded-xl transition">{{ __('site.about.nav.roadmap') }}</a>
        </div>
    </section>
</x-site.layout>
