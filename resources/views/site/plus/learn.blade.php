<x-site.borrower-layout :title="brand_title(__('plus.learn.title'))" active="dashboard">
    <div class="space-y-4">
        <h1 class="text-xl font-semibold">{{ __('plus.learn.title') }}</h1>
        @forelse ($lessons as $lesson)
            <a href="{{ route('site.borrower.plus.lesson', $lesson) }}" class="block rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                <p class="font-semibold">{{ app()->getLocale() === 'sw' ? ($lesson->title_sw ?: $lesson->title_en) : $lesson->title_en }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ app()->getLocale() === 'sw' ? ($lesson->intro_sw ?: $lesson->intro_en) : $lesson->intro_en }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-600">{{ __('plus.learn.empty') }}</p>
        @endforelse
    </div>
</x-site.borrower-layout>
