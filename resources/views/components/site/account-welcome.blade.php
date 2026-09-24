@props([
    'welcome' => null,
    'standalone' => false,
])

@php
    $welcome = $welcome ?? (auth()->user() ? app(\App\Services\AccountWelcomeService::class)->forUser(auth()->user()) : null);
    $cardCount = $welcome ? count($welcome['cards']) : 0;
    $cardOfTemplate = $welcome
        ? __('account_welcome.card_of', ['current' => '%%', 'total' => $cardCount])
        : '';
@endphp

@if ($welcome)
    <div @class([
            'kf-account-welcome rounded-3xl overflow-hidden bg-gradient-to-br from-brand to-brand-light text-white shadow-lg ring-1 ring-brand/20',
            'mb-6' => ! $standalone,
        ])
         x-data="{
            i: 0,
            startX: 0,
            startY: 0,
            cards: {{ $cardCount }},
            next() { this.i = Math.min(this.i + 1, this.cards - 1) },
            prev() { this.i = Math.max(this.i - 1, 0) },
            swipe(e) {
                const t = e.changedTouches?.[0];
                if (! t) return;
                const dx = t.clientX - this.startX;
                const dy = t.clientY - this.startY;
                if (Math.abs(dx) < 40 || Math.abs(dx) <= Math.abs(dy)) return;
                if (dx < 0) this.next();
                else this.prev();
            }
         }"
         @touchstart.passive="
            if ($event.target.closest('button, a, input, label')) return;
            startX = $event.changedTouches[0].clientX;
            startY = $event.changedTouches[0].clientY;
         "
         @touchend="swipe($event)">
        <div @class([
                'flex flex-col px-5 pt-5 pb-[max(1.25rem,env(safe-area-inset-bottom,0px))] max-h-[calc(100dvh-5.5rem)] sm:max-h-none',
                'sm:min-h-[30rem] sm:p-10' => $standalone,
                'sm:min-h-[28rem] sm:p-8' => ! $standalone,
            ])>
            {{-- Top: welcome + Skip + card counter --}}
            <div class="shrink-0">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-[10px] sm:text-[11px] uppercase tracking-[0.18em] text-brand-gold font-semibold pt-0.5">{{ __('account_welcome.kicker') }}</p>
                    <form method="POST" action="{{ route('site.account-welcome.complete') }}">
                        @csrf
                        <input type="hidden" name="audience" value="{{ $welcome['audience'] }}">
                        <button type="submit" class="text-xs sm:text-sm font-semibold text-white/80 hover:text-white px-1 py-0.5">{{ __('account_welcome.skip') }}</button>
                    </form>
                </div>
                <p class="mt-2 text-[11px] font-semibold uppercase tracking-widest text-white/65"
                   x-text="'{{ $cardOfTemplate }}'.replace('%%', i + 1)">
                    {{ __('account_welcome.card_of', ['current' => 1, 'total' => $cardCount]) }}
                </p>
            </div>

            {{-- Content: illustration + copy. Scrolls only if a translation runs long. --}}
            <div class="relative flex-1 min-h-0 overflow-y-auto overscroll-contain mt-3 sm:mt-4">
                @foreach ($welcome['cards'] as $index => $card)
                    <div x-show="i === {{ $index }}"
                         @if ($index > 0) x-cloak @endif
                         class="flex flex-col items-center text-center px-1 sm:px-2 pb-1">
                        <div @class([
                                'kf-welcome-art shrink-0 flex items-center justify-center my-2 sm:my-4',
                                'h-[4.75rem] w-full max-w-[8.5rem] sm:h-28 sm:max-w-[11rem]',
                                'kf-welcome-art-rewards' => ($card['variant'] ?? '') === 'rewards',
                            ])>
                            @include('components.site.illustrations.product', ['type' => $card['illustration'] ?? 'wallet'])
                        </div>

                        @if (($card['variant'] ?? '') === 'rewards')
                            <p class="shrink-0 text-[10px] sm:text-[11px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ __('borrower.rewards.eyebrow') }}</p>
                        @endif

                        <h2 @class([
                                'shrink-0 font-bold leading-[1.15] tracking-normal whitespace-pre-wrap mt-2 max-w-md',
                                'text-[1.75rem] sm:text-3xl' => ! $standalone,
                                'text-[1.75rem] sm:text-4xl' => $standalone,
                            ])>{{ $card['title'] }}</h2>

                        <p @class([
                                'shrink-0 text-white/85 leading-relaxed tracking-normal whitespace-pre-wrap mt-2 sm:mt-3 max-w-md',
                                'text-base sm:text-base' => ! $standalone,
                                'text-base sm:text-lg' => $standalone,
                            ])>{{ $card['body'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Bottom navigation: one reserved row — ‹ Back | dots | Next › --}}
            <nav class="kf-welcome-nav shrink-0 mt-4 pt-3 border-t border-white/12" aria-label="{{ __('account_welcome.kicker') }}">
                <div class="grid grid-cols-[minmax(4.75rem,1fr)_auto_minmax(4.75rem,1fr)] items-center gap-2">
                    <button type="button"
                            @click="prev()"
                            class="justify-self-start inline-flex items-center gap-0.5 rounded-lg px-1.5 py-2 text-sm font-semibold text-white/90 hover:text-white min-h-10 whitespace-nowrap"
                            :class="i === 0 ? 'invisible pointer-events-none' : ''"
                            :tabindex="i === 0 ? -1 : 0"
                            aria-label="{{ __('account_welcome.back') }}">
                        <span aria-hidden="true">‹</span>
                        <span>{{ __('account_welcome.back') }}</span>
                    </button>

                    <div class="flex items-center justify-center gap-1.5" role="tablist">
                        @foreach ($welcome['cards'] as $index => $card)
                            <button type="button" @click="i = {{ $index }}"
                                    class="size-2 rounded-full"
                                    :class="i === {{ $index }} ? 'bg-brand-gold scale-125' : 'bg-white/40'"
                                    aria-label="{{ $card['title'] }}"></button>
                        @endforeach
                    </div>

                    <div class="justify-self-end">
                        <button type="button"
                                @click="next()"
                                x-show="i < cards - 1"
                                class="inline-flex items-center gap-0.5 rounded-lg px-1.5 py-2 text-sm font-extrabold text-brand-gold hover:text-yellow-300 min-h-10 whitespace-nowrap"
                                aria-label="{{ __('account_welcome.next') }}">
                            <span>{{ __('account_welcome.next') }}</span>
                            <span aria-hidden="true">›</span>
                        </button>
                        <form method="POST" action="{{ route('site.account-welcome.complete') }}" x-show="i === cards - 1" x-cloak>
                            @csrf
                            <input type="hidden" name="audience" value="{{ $welcome['audience'] }}">
                            <button type="submit"
                                    class="inline-flex items-center gap-0.5 rounded-lg bg-brand-gold text-brand px-3 py-2 text-sm font-extrabold min-h-10 whitespace-nowrap"
                                    aria-label="{{ __('account_welcome.finish') }}">
                                {{ __('account_welcome.finish') }}
                            </button>
                        </form>
                    </div>
                </div>
            </nav>
        </div>
    </div>
@endif
