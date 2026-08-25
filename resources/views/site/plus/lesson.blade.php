<x-site.borrower-layout :title="brand_title($lesson->title_en)" active="dashboard">
    @php
        $title = app()->getLocale() === 'sw' ? ($lesson->title_sw ?: $lesson->title_en) : $lesson->title_en;
        $action = app()->getLocale() === 'sw' ? ($lesson->action_sw ?: $lesson->action_en) : $lesson->action_en;
    @endphp
    <div class="space-y-5">
        <x-site.plus-nav :back-url="route('site.borrower.plus.learn')" :back-label="__('plus.nav.learn')" />

        <x-site.plus-hero kicker="✦ {{ __('plus.learn.club') }} · {{ $lesson->month }}" :title="$title">
            <p class="text-sm text-white/80">{{ __('plus.learn.minutes', ['minutes' => $lesson->duration_minutes ?? 7]) }}</p>
        </x-site.plus-hero>

        <article class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 sm:p-6 space-y-4">
            @if ($videoUrl)
                <video class="w-full rounded-xl bg-black" controls controlsList="nodownload" src="{{ $videoUrl }}"></video>
            @endif
            <x-site.plus-article-steps :steps="$lesson->localizedSteps()">
                @if ($action)
                    <div class="rounded-xl bg-brand/5 ring-1 ring-brand/10 p-4">
                        <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.learn.action') }}</p>
                        <p class="text-sm font-semibold text-gray-900 mt-1">{{ $action }}</p>
                        <form method="post" action="{{ route('site.borrower.plus.lesson.action', $lesson) }}" class="mt-3">
                            @csrf
                            <button class="rounded-xl bg-brand text-white px-4 py-2.5 text-sm font-semibold">{{ __('plus.learn.try_now') }}</button>
                        </form>
                    </div>
                @endif
            </x-site.plus-article-steps>
            @unless ($progress->completed_at)
                <form method="post" action="{{ route('site.borrower.plus.lesson.complete', $lesson) }}">
                    @csrf
                    <button class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold">{{ __('plus.learn.mark_done') }}</button>
                </form>
            @endunless
        </article>
    </div>
</x-site.borrower-layout>
