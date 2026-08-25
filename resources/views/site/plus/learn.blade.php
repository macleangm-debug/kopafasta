<x-site.borrower-layout :title="brand_title(__('plus.learn.title'))" active="dashboard" content-width="wide">
    @php
        $featuredLesson = $lessons->first();
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $icons = $category_icons ?? collect();
        $browse = request()->boolean('browse');
        $hasMore = $has_more ?? false;
        $offset = (int) ($offset ?? 0);
    @endphp
    <div class="space-y-6">
        <x-site.plus-nav />

        <x-site.plus-hero kicker="Kopafasta Plus" :title="__('plus.learn.title')" :body="__('plus.learn.hero_body')">
            @if ($featuredLesson)
                @php
                    $clubTitle = $locale === 'sw' ? ($featuredLesson->title_sw ?: $featuredLesson->title_en) : $featuredLesson->title_en;
                @endphp
                <div class="rounded-2xl bg-white/10 ring-1 ring-white/15 p-4">
                    <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">✦ {{ __('plus.learn.club') }}</p>
                    <p class="font-bold mt-2">{{ $clubTitle }}</p>
                    <p class="text-sm text-white/80 mt-1">{{ __('plus.learn.minutes', ['minutes' => $featuredLesson->duration_minutes ?? 7]) }}</p>
                    <a href="{{ route('site.borrower.plus.lesson', $featuredLesson) }}" class="mt-3 inline-flex rounded-xl bg-brand-gold text-brand px-4 py-2 text-sm font-bold">{{ __('plus.learn.watch_now') }} →</a>
                </div>
            @endif
        </x-site.plus-hero>

        <form method="get" action="{{ route('site.borrower.plus.learn') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-3 flex gap-2">
            <input name="q" value="{{ $search }}" placeholder="🔎 {{ __('plus.learn.search') }}" class="flex-1 min-h-11 rounded-xl border-0 text-sm">
            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">{{ __('plus.learn.read') }}</button>
        </form>

        @if ($search || $category)
            <div class="space-y-3">
                @if ($category)
                    <p class="text-sm font-semibold text-gray-700">{{ $categories->firstWhere('slug', $category)?->localizedTitle() }}</p>
                @endif
                @forelse ($results as $subject)
                    @include('site.plus._subject-card', ['subject' => $subject])
                @empty
                    <p class="text-sm text-gray-600">{{ __('plus.learn.empty') }}</p>
                @endforelse
                @if ($hasMore)
                    <a href="{{ route('site.borrower.plus.learn', array_filter(['q' => $search, 'category' => $category, 'offset' => $offset + 12])) }}"
                       class="inline-flex text-sm font-semibold text-brand">{{ __('plus.load_more') }}</a>
                @endif
            </div>
        @elseif ($browse)
            <section>
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.browse') }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ($categories as $cat)
                        <a href="{{ route('site.borrower.plus.learn', ['category' => $cat->slug]) }}" class="rounded-2xl bg-white ring-1 ring-gray-100 px-4 py-3 flex items-center gap-3">
                            <span class="text-2xl">{{ $icons[$cat->slug] ?? '📘' }}</span>
                            <span class="font-semibold text-gray-900">{{ $cat->localizedTitle() }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            @if ($continue->isNotEmpty())
                <div>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.continue') }}</p>
                    <div class="flex gap-4 overflow-x-auto snap-x pb-2">
                        @foreach ($continue->take(5) as $subject)
                            <div class="snap-start shrink-0 w-[min(280px,calc(100vw-3rem))]">
                                @include('site.plus._subject-card', ['subject' => $subject, 'progress' => $progress[$subject->id] ?? null])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($for_you->isNotEmpty())
            <div>
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.for_you') }}</p>
                <div class="flex gap-4 overflow-x-auto snap-x pb-2">
                    @foreach ($for_you->take(5) as $subject)
                        <div class="snap-start shrink-0 w-[min(280px,calc(100vw-3rem))]">
                            @include('site.plus._subject-card', ['subject' => $subject])
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if ($saved->isNotEmpty())
                <div>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.saved') }}</p>
                    <div class="flex gap-4 overflow-x-auto snap-x pb-2">
                        @foreach ($saved->take(5) as $subject)
                            <div class="snap-start shrink-0 w-[min(280px,calc(100vw-3rem))]">
                                @include('site.plus._subject-card', ['subject' => $subject])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (($featured ?? collect())->isNotEmpty())
                <div>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.featured') }}</p>
                    <div class="flex gap-4 overflow-x-auto snap-x pb-2">
                        @foreach ($featured->take(5) as $subject)
                            <div class="snap-start shrink-0 w-[min(280px,calc(100vw-3rem))]">
                                @include('site.plus._subject-card', ['subject' => $subject])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <a href="{{ route('site.borrower.plus.learn', ['browse' => 1]) }}" class="inline-flex text-sm font-semibold text-brand">{{ __('plus.learn.browse_all') }} →</a>
        @endif
    </div>
</x-site.borrower-layout>
