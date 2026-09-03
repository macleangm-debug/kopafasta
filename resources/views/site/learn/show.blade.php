<x-site.layout :title="$seo['title'] ?? $subject->localizedTitle()" :description="$seo['description'] ?? $subject->localizedIntro()" :seo="$seo">
    <article class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        <a href="{{ route('site.learn.category', $category->slug) }}" class="text-sm text-brand font-semibold hover:underline">← {{ $category->localizedTitle() }}</a>
        <p class="mt-6 text-xs uppercase tracking-widest text-brand font-semibold">{{ $category->localizedTitle() }}</p>
        <h1 class="mt-2 text-3xl sm:text-4xl font-bold text-gray-900">{{ $subject->localizedTitle() }}</h1>
        @if ($subject->localizedIntro())
            <p class="mt-4 text-lg text-gray-700 leading-relaxed">{{ $subject->localizedIntro() }}</p>
        @endif

        <div class="mt-8 space-y-4 text-gray-700 leading-relaxed">
            @foreach (($editorial['opening'] ?? []) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
            @foreach (($editorial['cards'] ?? []) as $card)
                <p>{{ $card }}</p>
            @endforeach
        </div>

        <div class="mt-10 rounded-2xl bg-brand/5 ring-1 ring-brand/10 p-5">
            <p class="text-sm font-semibold text-gray-900">{{ __('site.plus.teaser_title') }}</p>
            <p class="mt-1 text-sm text-gray-600">{{ __('site.plus.optional') }}</p>
            <a href="{{ route('site.plus') }}" class="mt-4 inline-flex rounded-xl bg-brand text-white px-4 py-2.5 text-sm font-semibold">{{ __('site.plus.explore') }}</a>
        </div>
    </article>
</x-site.layout>
