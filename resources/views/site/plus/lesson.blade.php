<x-site.borrower-layout :title="brand_title($lesson->title_en)" active="dashboard">
    <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 space-y-4">
        <h1 class="text-xl font-semibold">{{ app()->getLocale() === 'sw' ? ($lesson->title_sw ?: $lesson->title_en) : $lesson->title_en }}</h1>
        <p class="text-sm text-gray-600">{{ app()->getLocale() === 'sw' ? ($lesson->intro_sw ?: $lesson->intro_en) : $lesson->intro_en }}</p>
        @if ($videoUrl)
            <video class="w-full rounded-xl bg-black" controls controlsList="nodownload" src="{{ $videoUrl }}"></video>
        @endif
        @if ($lesson->action_en)
            <p class="text-sm font-medium">{{ app()->getLocale() === 'sw' ? ($lesson->action_sw ?: $lesson->action_en) : $lesson->action_en }}</p>
        @endif
    </div>
</x-site.borrower-layout>
