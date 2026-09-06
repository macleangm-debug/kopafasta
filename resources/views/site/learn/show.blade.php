<x-site.layout :title="$seo['title'] ?? $subject->localizedTitle()" :description="$seo['description'] ?? $subject->localizedIntro()" :seo="$seo">
    <x-site.public-hero
        variant="compact"
        :eyebrow="$category->localizedTitle()"
        :title="$subject->localizedTitle()"
        :body="$subject->localizedIntro()"
    />

    <x-site.public-section narrow>
        <a href="{{ route('site.learn.category', $category->slug) }}" class="text-sm text-brand font-semibold hover:underline">← {{ $category->localizedTitle() }}</a>

        <div class="mt-8 space-y-4 text-gray-700 leading-relaxed">
            @foreach (($editorial['opening'] ?? []) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
            @foreach (($editorial['cards'] ?? []) as $card)
                <x-site.public-card class="!shadow-none">{{ $card }}</x-site.public-card>
            @endforeach
        </div>

        <div class="mt-10">
            <x-site.public-cta-band
                :title="__('site.plus.teaser_title')"
                :body="__('site.plus.optional')"
                :primary-href="route('site.plus')"
                :primary-label="__('site.plus.explore')"
            />
        </div>
    </x-site.public-section>
</x-site.layout>
