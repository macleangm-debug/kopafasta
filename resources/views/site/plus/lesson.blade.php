<x-site.borrower-layout :title="brand_title($lesson->title_en)" active="dashboard">
    @php
        $title = app()->getLocale() === 'sw' ? ($lesson->title_sw ?: $lesson->title_en) : $lesson->title_en;
        $intro = app()->getLocale() === 'sw' ? ($lesson->intro_sw ?: $lesson->intro_en) : $lesson->intro_en;
        $action = app()->getLocale() === 'sw' ? ($lesson->action_sw ?: $lesson->action_en) : $lesson->action_en;
    @endphp
    <article class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 sm:p-6 space-y-4">
        <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ $lesson->month }} · {{ __('plus.learn.minutes', ['minutes' => $lesson->duration_minutes ?? 7]) }}</p>
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
        <div class="text-sm text-gray-700 leading-relaxed space-y-3 whitespace-pre-line">{{ $intro }}</div>
        @if ($videoUrl)
            <video class="w-full rounded-xl bg-black" controls controlsList="nodownload" src="{{ $videoUrl }}"></video>
        @endif
        @if ($action)
            <div class="rounded-xl bg-brand/5 ring-1 ring-brand/10 p-4">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.learn.action') }}</p>
                <p class="text-sm font-semibold text-gray-900 mt-1">{{ $action }}</p>
            </div>
        @endif
    </article>
</x-site.borrower-layout>
