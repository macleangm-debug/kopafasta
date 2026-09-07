<x-site.borrower-layout :title="brand_title(__('plus.learn.title'))" active="plus" content-width="wide">
    @php
        $featuredLesson = $lessons->first();
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $icons = $category_icons ?? collect();
        $categories = collect($categories ?? []);
        $hasMore = $has_more ?? false;
        $offset = (int) ($offset ?? 0);
        $forYouTab = collect($for_you ?? [])
            ->concat($featured ?? [])
            ->unique('id')
            ->values()
            ->take(5);
        $continueTab = collect($continue ?? [])->take(5);
        $savedTab = collect($saved ?? [])->take(5);
        $featuredCats = $categories->take(4);
        $browseCats = $categories->slice(4)->values();
    @endphp
    <div class="space-y-8">
        <x-site.plus-nav />

        @if ($featuredLesson && ! $search && ! $category)
            @php
                $clubTitle = $locale === 'sw' ? ($featuredLesson->title_sw ?: $featuredLesson->title_en) : $featuredLesson->title_en;
            @endphp
            <section class="rounded-2xl bg-gradient-to-br from-brand to-brand-light text-white px-5 py-5 sm:px-6">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">✦ {{ __('plus.learn.club') }}</p>
                <p class="font-bold mt-2 text-lg">{{ $clubTitle }}</p>
                <p class="text-sm text-white/80 mt-1">{{ __('plus.learn.minutes', ['minutes' => $featuredLesson->duration_minutes ?? 7]) }}</p>
                <a href="{{ route('site.borrower.plus.lesson', $featuredLesson) }}" class="mt-3 inline-flex rounded-xl bg-brand-gold text-brand px-4 py-2 text-sm font-bold">{{ __('plus.learn.watch_now') }} →</a>
            </section>
        @endif

        <form method="get" action="{{ route('site.borrower.plus.learn') }}" class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm p-3 flex gap-2">
            <input name="q" value="{{ $search }}" placeholder="{{ __('plus.learn.search') }}" class="flex-1 min-h-11 rounded-xl border-0 bg-brand/5 px-3 text-sm focus:ring-2 focus:ring-brand/20">
            @if ($category)
                <input type="hidden" name="category" value="{{ $category }}">
            @endif
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
                <a href="{{ route('site.borrower.plus.learn') }}" class="inline-flex text-sm font-semibold text-brand">← {{ __('plus.learn.browse') }}</a>
            </div>
        @else
                <div x-data="{ tab: 'for_you' }" class="space-y-4">
                    <nav class="grid grid-cols-3 gap-1 p-1 rounded-2xl bg-brand/5 ring-1 ring-brand/10" role="tablist">
                        @foreach ([
                            'for_you' => __('plus.learn.for_you'),
                            'continue' => __('plus.learn.tab_continue'),
                            'saved' => __('plus.learn.saved'),
                        ] as $key => $label)
                            <button type="button" @click="tab = @js($key)" role="tab"
                                    class="min-w-0 px-1.5 sm:px-2 py-2.5 rounded-xl text-[11px] sm:text-sm font-bold tracking-tight transition text-center"
                                    :class="tab === @js($key) ? 'bg-brand text-white shadow-sm' : 'text-brand/70 hover:bg-white hover:text-brand'">
                                {{ $label }}
                            </button>
                        @endforeach
                    </nav>

                    <div x-show="tab === 'for_you'" class="space-y-3">
                        @forelse ($forYouTab as $subject)
                            @include('site.plus._subject-card', ['subject' => $subject, 'progress' => $progress[$subject->id] ?? null])
                        @empty
                            <p class="text-sm text-gray-600">{{ __('plus.learn.empty') }}</p>
                        @endforelse
                    </div>
                    <div x-show="tab === 'continue'" x-cloak class="space-y-3">
                        @forelse ($continueTab as $subject)
                            @include('site.plus._subject-card', ['subject' => $subject, 'progress' => $progress[$subject->id] ?? null])
                        @empty
                            <p class="text-sm text-gray-600">{{ __('plus.learn.empty_continue') }}</p>
                        @endforelse
                    </div>
                    <div x-show="tab === 'saved'" x-cloak class="space-y-3">
                        @forelse ($savedTab as $subject)
                            @include('site.plus._subject-card', ['subject' => $subject, 'progress' => $progress[$subject->id] ?? null])
                        @empty
                            <p class="text-sm text-gray-600">{{ __('plus.learn.empty_saved') }}</p>
                        @endforelse
                    </div>
                </div>

            @if ($categories->isNotEmpty())
                <section class="space-y-4">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold">{{ __('plus.learn.browse') }}</p>

                    @if ($featuredCats->isNotEmpty())
                        <div class="flex gap-3 overflow-x-auto pb-1 snap-x snap-mandatory scrollbar-none lg:grid lg:grid-cols-4 lg:overflow-visible lg:gap-3">
                            @foreach ($featuredCats as $cat)
                                <a href="{{ route('site.borrower.plus.learn', ['category' => $cat->slug]) }}"
                                   class="snap-start shrink-0 w-[min(82vw,18rem)] lg:w-auto rounded-2xl bg-gradient-to-br from-brand to-brand-light text-white px-4 py-4 hover:brightness-105 shadow-sm">
                                    <span class="size-10 rounded-xl bg-white/15 text-xl grid place-items-center">{{ $icons[$cat->slug] ?? '📘' }}</span>
                                    <span class="mt-3 block font-bold leading-snug">{{ $cat->localizedTitle() }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($browseCats->isNotEmpty())
                        <div class="flex gap-2.5 overflow-x-auto pb-1 snap-x snap-mandatory scrollbar-none lg:grid lg:grid-cols-5 lg:overflow-visible lg:gap-2.5">
                            @foreach ($browseCats as $cat)
                                <a href="{{ route('site.borrower.plus.learn', ['category' => $cat->slug]) }}"
                                   class="snap-start shrink-0 w-[min(42vw,9.5rem)] lg:w-auto rounded-xl bg-white ring-1 ring-brand/10 px-3 py-3 hover:ring-brand/30 text-center">
                                    <span class="mx-auto size-9 rounded-lg bg-brand/10 text-lg grid place-items-center">{{ $icons[$cat->slug] ?? '📘' }}</span>
                                    <span class="mt-2 block text-xs font-semibold text-gray-900 leading-snug line-clamp-2">{{ $cat->localizedTitle() }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        @endif
    </div>
</x-site.borrower-layout>
