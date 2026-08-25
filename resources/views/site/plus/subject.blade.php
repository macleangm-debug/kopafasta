<x-site.borrower-layout :title="brand_title($subject->localizedTitle())" active="dashboard">
    <article class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 sm:p-6 space-y-4">
        <a href="{{ route('site.borrower.plus.learn') }}" class="text-sm font-semibold text-brand">← {{ __('plus.learn.title') }}</a>
        <p class="text-xs font-semibold text-brand">{{ $subject->category?->localizedTitle() }} · {{ __('plus.learn.minutes', ['minutes' => $subject->duration_minutes]) }}</p>
        <h1 class="text-2xl font-bold text-gray-900">{{ $subject->localizedTitle() }}</h1>
        <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $subject->localizedBody() ?: $subject->localizedIntro() }}</div>
        @if ($subject->localizedAction())
            <div class="rounded-xl bg-brand/5 ring-1 ring-brand/10 p-4">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.learn.try_now') }}</p>
                <p class="text-sm font-semibold text-gray-900 mt-1">{{ $subject->localizedAction() }}</p>
                <form method="post" action="{{ route('site.borrower.plus.subject.action', $subject) }}" class="mt-3">
                    @csrf
                    <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">{{ $subject->localizedAction() }} →</button>
                </form>
            </div>
        @endif
        <div class="flex flex-wrap gap-2">
            <form method="post" action="{{ route('site.borrower.plus.subject.complete', $subject) }}">
                @csrf
                <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">{{ __('plus.learn.mark_done') }}</button>
            </form>
            <form method="post" action="{{ route('site.borrower.plus.subject.save', $subject) }}">
                @csrf
                <button class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2 text-sm font-semibold">{{ $progress->saved_at ? __('plus.learn.saved_on') : __('plus.learn.save') }}</button>
            </form>
        </div>
    </article>
</x-site.borrower-layout>
