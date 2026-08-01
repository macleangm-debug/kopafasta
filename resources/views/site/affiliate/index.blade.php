<x-site.layout :title="brand_title(__('site.affiliate.title'))">
    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
            <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold mb-3">{{ __('site.affiliate.title') }}</p>
            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight max-w-2xl">{{ __('site.affiliate.hero_title') }}</h1>
            <p class="mt-5 text-lg text-white/80 max-w-xl leading-relaxed">{{ __('site.affiliate.hero_body') }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('site.affiliate.apply') }}"
                   class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-8 py-4 rounded-xl shadow-lg transition">
                    {{ __('site.affiliate.cta_apply') }}
                </a>
                <a href="{{ route('site.login.partner') }}"
                   class="inline-flex items-center gap-2 glass-card-dark font-semibold px-6 py-4 rounded-xl transition hover:bg-white/10">
                    {{ __('site.affiliate.portal_title') }} →
                </a>
            </div>
        </div>
    </section>

    <section class="py-14 bg-[#faf8f5] border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ([
                    __('site.affiliate.benefit_1'),
                    __('site.affiliate.benefit_2'),
                    __('site.affiliate.benefit_3'),
                    __('site.affiliate.benefit_4'),
                ] as $benefit)
                    <div class="glass-card p-5 flex items-start gap-3">
                        <span class="size-8 rounded-xl bg-brand text-white grid place-items-center text-sm font-bold shrink-0">✓</span>
                        <p class="text-sm font-semibold text-gray-800 leading-snug">{{ $benefit }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-center mb-10">{{ __('site.affiliate.how_it_works') }}</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach ([
                    [1, __('site.affiliate.step_1'), __('site.affiliate.step_1_body')],
                    [2, __('site.affiliate.step_2'), __('site.affiliate.step_2_body')],
                    [3, __('site.affiliate.step_3'), __('site.affiliate.step_3_body')],
                    [4, __('site.affiliate.step_4'), __('site.affiliate.step_4_body')],
                ] as [$num, $title, $body])
                    <div class="glass-card p-6 relative overflow-hidden">
                        <span class="absolute -right-3 -top-3 text-6xl font-black text-brand/5">{{ $num }}</span>
                        <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('site.affiliate.step_label', ['num' => $num]) }}</p>
                        <h3 class="font-bold text-gray-900 mt-2">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-16 bg-[#faf8f5]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-8 items-center">
            <div class="glass-card p-8">
                <h2 class="text-xl font-bold text-gray-900">{{ __('site.affiliate.commission_title') }}</h2>
                <p class="mt-3 text-sm text-gray-600 leading-relaxed">{{ __('site.affiliate.commission_body') }}</p>
                <ul class="mt-6 space-y-3 text-sm">
                    @foreach ([__('site.affiliate.type_individual'), __('site.affiliate.type_company')] as $type)
                        <li class="flex items-center gap-2 text-gray-700">
                            <span class="size-6 rounded-full bg-brand-muted text-brand grid place-items-center text-xs">●</span>
                            {{ $type }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-xs text-gray-500">{{ __('site.affiliate.type_hint') }}</p>
            </div>
            <div class="glass-card p-8 bg-brand text-white relative overflow-hidden">
                <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
                <div class="relative">
                    <h2 class="text-xl font-bold">{{ __('site.affiliate.portal_title') }}</h2>
                    <p class="mt-3 text-sm text-white/80 leading-relaxed">{{ __('site.affiliate.portal_body') }}</p>
                    <p class="mt-4 text-sm text-white/70">{{ __('site.affiliate.after_approval') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-brand text-white text-center">
        <div class="max-w-2xl mx-auto px-4">
            <h2 class="text-2xl sm:text-3xl font-bold">{{ __('site.affiliate.cta_heading') }}</h2>
            <p class="mt-3 text-white/80">{{ __('site.affiliate.subtitle') }}</p>
            <a href="{{ route('site.affiliate.apply') }}"
               class="mt-8 inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-10 py-4 rounded-xl shadow-lg transition">
                {{ __('site.affiliate.cta_apply') }}
            </a>
        </div>
    </section>
</x-site.layout>
