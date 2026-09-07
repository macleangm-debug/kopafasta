<x-site.borrower-layout :title="brand_title($subject->localizedTitle())" active="plus" content-width="wide">
    @php
        $editorial = $subject->localizedEditorial();
        $related = $related ?? collect();
    @endphp
    <div class="space-y-5">
        <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_16rem] lg:gap-6 lg:items-start">
            <article class="space-y-5 min-w-0">
                <x-site.plus-hero
                    kicker="{{ $subject->category?->localizedTitle() }} · {{ __('plus.learn.minutes', ['minutes' => $subject->duration_minutes]) }}"
                    :title="$subject->localizedTitle()"
                    :back-url="route('site.borrower.plus.learn')"
                    :back-label="__('plus.nav.learn')"
                />

                <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 sm:p-6 space-y-4 w-full">
                    <x-site.plus-article-steps
                        :opening="$editorial['opening']"
                        :cards="$editorial['cards']"
                        :slides="$editorial['slides'] ?? null"
                        :complete-url="route('site.borrower.plus.subject.complete', $subject)"
                        :completed="(bool) $progress->completed_at"
                    >
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
                    <form method="post" action="{{ route('site.borrower.plus.subject.save', $subject) }}">
                        @csrf
                        <button class="rounded-xl bg-brand text-white px-4 py-2.5 text-sm font-semibold">
                            {{ $progress->saved_at ? '♥ '.__('plus.learn.saved_on') : '♡ '.__('plus.learn.save') }}
                        </button>
                    </form>
                </div>
            </article>

            @if ($related->isNotEmpty())
                <aside class="space-y-3 min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold">{{ __('plus.learn.related') }}</p>
                    <div class="hidden lg:block space-y-2">
                        @foreach ($related as $item)
                            <a href="{{ route('site.borrower.plus.subject', $item) }}"
                               class="block rounded-xl bg-white ring-1 ring-gray-200 px-3 py-3 hover:ring-brand/30">
                                <p class="text-sm font-semibold text-gray-900 line-clamp-2">{{ $item->localizedTitle() }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ __('plus.learn.minutes', ['minutes' => $item->duration_minutes]) }}</p>
                            </a>
                        @endforeach
                    </div>
                    <div class="lg:hidden flex gap-3 overflow-x-auto pb-1 snap-x snap-mandatory">
                        @foreach ($related as $item)
                            <a href="{{ route('site.borrower.plus.subject', $item) }}"
                               class="snap-start shrink-0 w-[min(72vw,14rem)] rounded-xl bg-white ring-1 ring-gray-200 px-3 py-3">
                                <p class="text-sm font-semibold text-gray-900 line-clamp-2">{{ $item->localizedTitle() }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ __('plus.learn.minutes', ['minutes' => $item->duration_minutes]) }}</p>
                            </a>
                        @endforeach
                    </div>
                </aside>
            @endif
        </div>
    </div>
</x-site.borrower-layout>
