<x-site.layout :title="brand_title(__('site.country.coming_soon_title', ['country' => $country['name']]))">
    <section class="min-h-[60vh] flex items-center justify-center bg-[#faf8f5] px-4 py-20">
        <div class="max-w-lg text-center">
            <div class="text-5xl mb-4">{{ $country['emoji'] ?? '🌍' }}</div>
            <h1 class="text-3xl font-bold text-brand">{{ __('site.country.coming_soon_title', ['country' => $country['name']]) }}</h1>
            <p class="mt-4 text-gray-600 leading-relaxed">{{ __('site.country.coming_soon_body') }}</p>
            <div class="mt-8 flex flex-wrap gap-3 justify-center">
                <form method="POST" action="{{ route('site.country.update') }}">
                    @csrf
                    <input type="hidden" name="country" value="TZ">
                    <button class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-lg transition">
                        {{ __('site.country.switch_tanzania') }}
                    </button>
                </form>
                <a href="{{ route('site.home') }}" class="inline-flex items-center border border-brand/30 text-brand font-semibold px-6 py-3 rounded-lg hover:bg-brand-muted transition">
                    {{ __('site.country.back_home') }}
                </a>
            </div>
        </div>
    </section>
</x-site.layout>
