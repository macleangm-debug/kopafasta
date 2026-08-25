<x-site.borrower-layout :title="brand_title(__('plus.learn.title'))" active="dashboard" content-width="wide">
    @php
        $featuredLesson = $lessons->first();
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
    @endphp
    <div class="space-y-6">
        <a href="{{ route('site.borrower.plus.home') }}" class="text-sm font-semibold text-brand">← Plus</a>
        <div>
            <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">Kopafasta Plus</p>
            <h1 class="text-xl font-bold text-gray-900 mt-1">{{ __('plus.learn.title') }}</h1>
            <p class="text-sm text-gray-600 mt-1">{{ __('plus.learn.tagline') }}</p>
            <p class="text-sm text-gray-500">{{ __('plus.learn.intro') }}</p>
        </div>

        @if ($featuredLesson)
            @php
                $clubTitle = $locale === 'sw' ? ($featuredLesson->title_sw ?: $featuredLesson->title_en) : $featuredLesson->title_en;
                $clubAction = $locale === 'sw' ? ($featuredLesson->action_sw ?: $featuredLesson->action_en) : $featuredLesson->action_en;
            @endphp
            <a href="{{ route('site.borrower.plus.lesson', $featuredLesson) }}" class="block kf-premium-panel rounded-2xl p-5 sm:p-6">
                <p class="relative text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">✦ {{ __('plus.learn.club') }}</p>
                <p class="relative text-sm text-white/80 mt-2">{{ $featuredLesson->month }}</p>
                <h2 class="relative text-xl font-bold mt-1">{{ $clubTitle }}</h2>
                <p class="relative text-sm text-white/80 mt-1">{{ __('plus.learn.minutes', ['minutes' => $featuredLesson->duration_minutes ?? 7]) }}</p>
                @if ($clubAction)
                    <p class="relative text-sm mt-3">{{ __('plus.learn.action') }}: {{ $clubAction }}</p>
                @endif
                <span class="relative mt-4 inline-flex rounded-xl bg-brand-gold text-brand px-4 py-2 text-sm font-bold">{{ __('plus.learn.watch_now') }}</span>
            </a>
        @endif

        <form method="get" action="{{ route('site.borrower.plus.learn') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-3 flex gap-2">
            <input name="q" value="{{ $search }}" placeholder="🔎 {{ __('plus.learn.search') }}" class="flex-1 min-h-11 rounded-xl border-0 text-sm">
            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">{{ __('plus.learn.read') }}</button>
        </form>

        <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
            <a href="{{ route('site.borrower.plus.learn') }}" class="shrink-0 rounded-full px-3 py-1.5 text-sm ring-1 {{ ! $category ? 'bg-brand text-white ring-brand' : 'bg-white ring-gray-200' }}">{{ __('plus.learn.for_you') }}</a>
            @foreach ($categories as $cat)
                <a href="{{ route('site.borrower.plus.learn', ['category' => $cat->slug]) }}" class="shrink-0 rounded-full px-3 py-1.5 text-sm ring-1 {{ $category === $cat->slug ? 'bg-brand text-white ring-brand' : 'bg-white ring-gray-200' }}">{{ $cat->localizedTitle() }}</a>
            @endforeach
        </div>

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
                        @foreach ($continue as $subject)
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

            @if ($featured->isNotEmpty())
                <div>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.featured') }}</p>
                    <div class="flex gap-3 overflow-x-auto pb-1">
                        @foreach ($featured as $subject)
                            <a href="{{ route('site.borrower.plus.subject', $subject) }}" class="min-w-[220px] rounded-2xl bg-white ring-1 ring-gray-200 p-4">
                                <p class="text-xs text-brand font-semibold">{{ $subject->category?->localizedTitle() }}</p>
                                <p class="font-semibold text-gray-900 mt-1">{{ $subject->localizedTitle() }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ __('plus.learn.minutes', ['minutes' => $subject->duration_minutes]) }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($saved->isNotEmpty())
                <div>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.saved') }}</p>
                    @foreach ($saved as $subject)
                        @include('site.plus._subject-card', ['subject' => $subject])
                    @endforeach
                </div>
            @endif

            <div>
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.browse') }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ($categories as $cat)
                        <a href="{{ route('site.borrower.plus.learn', ['category' => $cat->slug]) }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-4">
                            <p class="font-semibold text-gray-900">{{ $cat->localizedTitle() }}</p>
                            <p class="text-sm text-brand mt-1">{{ __('plus.learn.more') }} →</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($lessons->count() > 1)
            <div>
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.learn.library') }}</p>
                @foreach ($lessons->skip(1) as $lesson)
                    @php $title = $locale === 'sw' ? ($lesson->title_sw ?: $lesson->title_en) : $lesson->title_en; @endphp
                    <a href="{{ route('site.borrower.plus.lesson', $lesson) }}" class="block rounded-2xl bg-white ring-1 ring-gray-200 p-4 mb-2">
                        <p class="text-xs text-brand font-semibold">{{ $lesson->month }}</p>
                        <p class="font-semibold text-gray-900 mt-1">{{ $title }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-site.borrower-layout>
