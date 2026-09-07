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
                    <a href="{{ route('site.learn.category', $category->slug) }}" class="text-sm font-semibold text-brand hover:underline shrink-0">{{ __('borrower.dashboard.view_all') }}</a>
                </div>
                <div class="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory scrollbar-none lg:grid lg:grid-cols-3 lg:overflow-visible lg:pb-0 lg:gap-4">
                    @foreach ($category->subjects as $subject)
                        <a href="{{ route('site.learn.show', [$category->slug, $subject->slug]) }}"
                           class="snap-start shrink-0 w-[min(91vw,22rem)] lg:w-auto h-full rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 flex flex-col hover:ring-brand/30 transition">
                            <p class="font-bold text-gray-900 line-clamp-2 min-h-[2.75rem]">{{ $subject->localizedTitle() }}</p>
                            <p class="mt-2 text-sm text-gray-600 line-clamp-3 flex-1">{{ \Illuminate\Support\Str::limit($subject->localizedIntro(), 140) }}</p>
                            <span class="mt-4 text-sm font-semibold text-brand">{{ __('site.products.learn_more') }} →</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-600">{{ __('seo.learn_description') }}</p>
        @endforelse
    </x-site.public-section>
</x-site.layout>
