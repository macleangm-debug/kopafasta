<x-site.layout :title="$seo['title'] ?? __('seo.learn_title')" :description="$seo['description'] ?? __('seo.learn_description')" :seo="$seo">
    <section class="bg-brand text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ brand_name() }}</p>
            <h1 class="mt-2 text-3xl sm:text-4xl font-bold">{{ __('seo.learn_title') }}</h1>
            <p class="mt-3 text-white/80 max-w-2xl">{{ __('seo.learn_description') }}</p>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
        @forelse ($categories as $category)
            <div>
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-xl font-bold text-gray-900">
                        <span class="mr-2">{{ $icons[$category->slug] ?? '📘' }}</span>{{ $category->localizedTitle() }}
                    </h2>
                    <a href="{{ route('site.learn.category', $category->slug) }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.products.learn_more') }}</a>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ($category->subjects as $subject)
                        <a href="{{ route('site.learn.show', [$category->slug, $subject->slug]) }}" class="glass-card p-5 hover:ring-brand/30 transition">
                            <h3 class="font-semibold text-gray-900">{{ $subject->localizedTitle() }}</h3>
                            <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ \Illuminate\Support\Str::limit($subject->localizedIntro(), 140) }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-600">{{ __('seo.learn_description') }}</p>
        @endforelse
    </section>
</x-site.layout>
