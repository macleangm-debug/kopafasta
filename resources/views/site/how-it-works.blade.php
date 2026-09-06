<x-site.layout :title="brand_title(__('site.how_it_works.title'))">
    <x-site.public-hero
        variant="feature"
        :eyebrow="__('site.how_it_works.title')"
        :title="__('site.how_it_works.title')"
        :body="__('site.how_it_works.subtitle')"
        :primary-href="route('site.register.borrower')"
        :primary-label="__('site.how_it_works.cta')"
    />

    <section class="py-16 lg:py-20 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid sm:grid-cols-2 gap-6">
                @foreach (__('site.how_it_works.steps') as $i => $step)
                    <div class="glass-card p-6 hover:shadow-lg transition relative overflow-hidden group">
                        <span class="size-10 rounded-full bg-brand text-white font-bold grid place-items-center text-sm">{{ $i + 1 }}</span>
                        <h3 class="mt-4 font-bold text-lg text-gray-900">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="premium-gradient py-16 border-y border-gray-100">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid sm:grid-cols-3 gap-6">
            @foreach (__('site.how_it_works.details') as $detail)
                <div class="text-center p-6">
                    <span class="text-brand-gold font-black tracking-[-0.14em] text-2xl" aria-hidden="true">›››</span>
                    <h3 class="mt-3 font-bold text-gray-900">{{ $detail['title'] }}</h3>
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $detail['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <a href="{{ route('site.register.borrower') }}" class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-8 py-4 rounded-xl transition shadow-md">
                {{ __('site.how_it_works.cta') }}
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
            </a>
        </div>
    </section>
</x-site.layout>
