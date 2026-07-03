<x-site.layout :title="brand_title(__('site.how_it_works.title'))">
    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold">{{ __('site.how_it_works.title') }}</h1>
            <p class="mt-3 text-white/80 max-w-xl mx-auto">{{ __('site.how_it_works.subtitle') }}</p>
        </div>
    </section>

    <section class="py-16 lg:py-20 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid sm:grid-cols-2 gap-6">
                @foreach (__('site.how_it_works.steps') as $i => $step)
                    <div class="glass-card p-6 hover:shadow-lg transition relative overflow-hidden group">
                        <div class="absolute top-4 right-4 text-6xl opacity-10 group-hover:opacity-20 transition">{{ $step['icon'] }}</div>
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
                    <div class="text-4xl mb-4">{{ $detail['icon'] }}</div>
                    <h3 class="font-bold text-gray-900">{{ $detail['title'] }}</h3>
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
