<x-site.borrower-layout :title="brand_title($subject->localizedTitle())" active="dashboard">
    <div class="space-y-5">
        <x-site.plus-nav :back-url="route('site.borrower.plus.learn')" :back-label="__('plus.nav.learn')" />

        <article class="space-y-5">
            <x-site.plus-hero kicker="{{ $subject->category?->localizedTitle() }} · {{ __('plus.learn.minutes', ['minutes' => $subject->duration_minutes]) }}" :title="$subject->localizedTitle()">
                @if ($progress->saved_at)
                    <p class="text-xs text-brand-gold font-semibold">{{ __('plus.learn.saved_on') }}</p>
                @endif
            </x-site.plus-hero>

            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 sm:p-6 space-y-4">
                <x-site.plus-article-steps :steps="$subject->localizedSteps()">
                    @if ($subject->localizedAction())
                        <div class="rounded-xl bg-brand/5 ring-1 ring-brand/10 p-4">
                            <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.learn.try_now') }}</p>
                            <p class="text-sm font-semibold text-gray-900 mt-1">{{ $subject->localizedAction() }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('plus.learn.try_hint') }}</p>
                            <form method="post" action="{{ route('site.borrower.plus.subject.action', $subject) }}" class="mt-3">
                                @csrf
                                <button class="rounded-xl bg-brand text-white px-4 py-2.5 text-sm font-semibold">{{ $subject->localizedAction() }} →</button>
                            </form>
                        </div>
                    @endif
                </x-site.plus-article-steps>
                <div class="flex flex-wrap gap-2">
                    <form method="post" action="{{ route('site.borrower.plus.subject.complete', $subject) }}">
                        @csrf
                        <button class="rounded-xl bg-brand text-white px-4 py-2.5 text-sm font-semibold">{{ __('plus.learn.mark_done') }}</button>
                    </form>
                    <form method="post" action="{{ route('site.borrower.plus.subject.save', $subject) }}">
                        @csrf
                        <button class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold">{{ $progress->saved_at ? __('plus.learn.saved_on') : __('plus.learn.save') }}</button>
                    </form>
                </div>
            </div>
        </article>
    </div>
</x-site.borrower-layout>
