<x-site.layout :title="$seo['title'] ?? __('seo.learn_title')" :description="$seo['description'] ?? __('seo.learn_description')" :seo="$seo">
    <x-site.public-hero
        variant="feature"
        :eyebrow="brand_name()"
        :title="__('seo.learn_title')"
        :body="__('seo.learn_description')"
    />

    <x-site.public-section>
        @forelse ($categories as $category)
            <div @class(['mt-10' => ! $loop->first])>
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-xl font-bold text-gray-900">{{ $category->localizedTitle() }}</h2>
                    <a href="{{ route('site.learn.category', $category->slug) }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.products.learn_more') }}</a>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($category->subjects as $subject)
                        <x-site.public-card
                            :title="$subject->localizedTitle()"
                            :href="route('site.learn.show', [$category->slug, $subject->slug])"
                            :cta="__('site.products.learn_more')"
                        >
                            {{ \Illuminate\Support\Str::limit($subject->localizedIntro(), 140) }}
                        </x-site.public-card>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-600">{{ __('seo.learn_description') }}</p>
        @endforelse
    </x-site.public-section>
</x-site.layout>
