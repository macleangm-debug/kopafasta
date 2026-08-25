<x-site.borrower-layout :title="brand_title(__('plus.learn.title'))" active="dashboard" content-width="wide">
    @php
        $featuredLesson = $lessons->first();
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $icons = $category_icons ?? collect();
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

        <section>
            <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.browse') }}</p>
            <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory pb-2 items-stretch -mx-1 px-1">
                @foreach ($categories as $cat)
                    <x-site.plus-room-card
                        :href="route('site.borrower.plus.learn', ['category' => $cat->slug])"
                        :icon="$icons[$cat->slug] ?? '📘'"
                        :title="$cat->localizedTitle()"
                        :cta="__('plus.learn.open_topic')"
                    />
                @endforeach
            </div>
        </section>

        @if ($search || $category)
            <div class="space-y-3">
                @forelse ($results as $subject)
                    @include('site.plus._subject-card', ['subject' => $subject])
                @empty
                    <p class="text-sm text-gray-600">{{ __('plus.learn.empty') }}</p>
                @endforelse
            </div>
        @else
            @if ($continue->isNotEmpty())
                <div>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.continue') }}</p>
                    <div class="space-y-3">
                        @foreach ($continue->take(2) as $subject)
                            @include('site.plus._subject-card', ['subject' => $subject, 'progress' => $progress[$subject->id] ?? null])
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.for_you') }}</p>
                <div class="space-y-3">
                    @foreach ($for_you as $subject)
                        @include('site.plus._subject-card', ['subject' => $subject])
                    @endforeach
                </div>
            </div>

            @if ($saved->isNotEmpty())
                <div>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.saved') }}</p>
                    @foreach ($saved->take(3) as $subject)
                        @include('site.plus._subject-card', ['subject' => $subject])
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-site.borrower-layout>
