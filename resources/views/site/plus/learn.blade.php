<x-site.borrower-layout :title="brand_title(__('plus.learn.title'))" active="plus" content-width="wide">
    @php
        $featuredLesson = $lessons->first();
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $icons = $category_icons ?? collect();
        $browse = request()->boolean('browse');
        $hasMore = $has_more ?? false;
        $offset = (int) ($offset ?? 0);
        $forYouTab = collect($for_you ?? [])
            ->concat($featured ?? [])
            ->unique('id')
            ->values()
            ->take(5);
        $continueTab = collect($continue ?? [])->take(5);
        $savedTab = collect($saved ?? [])->take(5);
        $allowedTabs = ['for_you', 'continue', 'saved'];
        $defaultTab = in_array(request('tab'), $allowedTabs, true) ? request('tab') : 'for_you';
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
            <div x-data="{ tab: @js($defaultTab) }" class="space-y-4">
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

                <div x-show="tab === 'for_you'" x-cloak class="space-y-3">
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

            <a href="{{ route('site.borrower.plus.learn', ['browse' => 1]) }}" class="inline-flex text-sm font-semibold text-brand">{{ __('plus.learn.browse_all') }} →</a>
        @endif
    </div>
</x-site.borrower-layout>
