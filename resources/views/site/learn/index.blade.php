<x-site.layout :title="$seo['title'] ?? __('seo.learn_title')" :description="$seo['description'] ?? __('seo.learn_description')" :seo="$seo">
    <x-site.public-hero
        variant="feature"
        :eyebrow="brand_name()"
        :title="__('seo.learn_title')"
        :body="__('seo.learn_description')"
    />

    <x-site.public-section>
        <form method="get" action="{{ route('site.learn') }}" class="mb-10 rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm p-3 flex gap-2 max-w-2xl">
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('plus.learn.search') }}" class="flex-1 min-h-11 rounded-xl border-0 bg-brand/5 px-3 text-sm" disabled>
            <a href="{{ route('site.learn') }}#topics" class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold inline-flex items-center">{{ __('plus.learn.browse') }}</a>
        </form>

        @if ($categories->isNotEmpty())
            <section id="topics" class="mb-12">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-4">{{ __('plus.learn.browse') }}</p>
                <div class="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory scrollbar-none lg:grid lg:grid-cols-4 lg:overflow-visible lg:gap-4">
                    @foreach ($categories->take(8) as $category)
                        <a href="{{ route('site.learn.category', $category->slug) }}"
                           class="snap-start shrink-0 w-[min(78vw,16rem)] lg:w-auto rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm px-4 py-4 hover:ring-brand/30 transition">
                            <span class="size-10 rounded-xl bg-brand/10 text-brand text-lg font-bold grid place-items-center">{{ strtoupper(substr($category->localizedTitle(), 0, 1)) }}</span>
                            <p class="mt-3 font-bold text-gray-900 leading-snug">{{ $category->localizedTitle() }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $category->subjects->count() }} {{ __('site.products.learn_more') }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @forelse ($categories as $category)
            <div @class(['mt-12' => ! $loop->first])>
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-xl font-bold text-gray-900">{{ $category->localizedTitle() }}</h2>
                    <a href="{{ route('site.learn.category', $category->slug) }}" class="text-sm font-semibold text-brand hover:underline shrink-0">{{ __('borrower.dashboard.view_all') }}</a>
                </div>
                <div class="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory scrollbar-none lg:grid lg:grid-cols-3 lg:overflow-visible lg:pb-0 lg:gap-4">
                    @foreach ($category->subjects->take(6) as $subject)
                        <a href="{{ route('site.learn.show', [$category->slug, $subject->slug]) }}"
                           class="snap-start shrink-0 w-[min(88vw,22rem)] lg:w-auto h-full rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 flex flex-col hover:ring-brand/30 transition">
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

        <div class="mt-14 rounded-3xl bg-gradient-to-br from-brand to-brand-light text-white px-6 py-8 sm:px-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">Kopafasta Plus</p>
                <p class="mt-2 text-xl font-bold">{{ __('plus.learn.title') }}</p>
                <p class="mt-1 text-sm text-white/80">{{ __('plus.learn.hero_body') }}</p>
            </div>
            <a href="{{ route('site.register.borrower') }}" class="inline-flex justify-center rounded-xl bg-brand-gold text-brand font-bold px-5 py-3 text-sm shrink-0">
                {{ __('plus.home.join') }}
            </a>
        </div>
    </x-site.public-section>
</x-site.layout>
