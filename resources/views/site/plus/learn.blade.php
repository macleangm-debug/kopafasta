<x-site.borrower-layout :title="brand_title(__('plus.learn.title'))" active="dashboard">
    <div class="space-y-4">
        <div>
            <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">Kopafasta Plus</p>
            <h1 class="text-xl font-semibold text-gray-900 mt-1">{{ __('plus.learn.title') }}</h1>
            <p class="text-sm text-gray-600 mt-1">{{ __('plus.learn.intro') }}</p>
        </div>
        @forelse ($lessons as $lesson)
            @php
                $title = app()->getLocale() === 'sw' ? ($lesson->title_sw ?: $lesson->title_en) : $lesson->title_en;
                $intro = app()->getLocale() === 'sw' ? ($lesson->intro_sw ?: $lesson->intro_en) : $lesson->intro_en;
            @endphp
            <a href="{{ route('site.borrower.plus.lesson', $lesson) }}" class="block rounded-2xl bg-white ring-1 ring-gray-200 p-5 hover:ring-brand/30 transition">
                <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-brand">
                    <span>{{ $lesson->month }}</span>
                    <span>·</span>
                    <span>{{ __('plus.learn.minutes', ['minutes' => $lesson->duration_minutes ?? 7]) }}</span>
                </div>
                <p class="font-semibold text-gray-900 mt-2">{{ $title }}</p>
                <p class="text-sm text-gray-600 mt-1 line-clamp-3">{{ $intro }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-600">{{ __('plus.learn.empty') }}</p>
        @endforelse
    </div>
</x-site.borrower-layout>
