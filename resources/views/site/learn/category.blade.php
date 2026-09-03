<x-site.layout :title="$seo['title'] ?? $category->localizedTitle()" :description="$seo['description'] ?? __('seo.learn_description')" :seo="$seo">
    <section class="bg-brand text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <a href="{{ route('site.learn') }}" class="text-sm text-white/70 hover:text-white">← {{ __('seo.footer_learn') }}</a>
            <h1 class="mt-4 text-3xl font-bold">{{ $icon ?? '📘' }} {{ $category->localizedTitle() }}</h1>
            <p class="mt-3 text-white/80">{{ __('seo.learn_description') }}</p>
        </div>
    </section>

    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-4">
        @forelse ($subjects as $subject)
            <a href="{{ route('site.learn.show', [$category->slug, $subject->slug]) }}" class="glass-card p-5 block hover:ring-brand/30 transition">
                <h2 class="font-semibold text-gray-900">{{ $subject->localizedTitle() }}</h2>
                <p class="mt-2 text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($subject->localizedIntro(), 180) }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-600">{{ __('seo.learn_description') }}</p>
        @endforelse
    </section>
</x-site.layout>
