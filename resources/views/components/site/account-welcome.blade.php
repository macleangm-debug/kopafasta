@props([
    'welcome' => null,
    'standalone' => false,
])

@php
    $welcome = $welcome ?? (auth()->user() ? app(\App\Services\AccountWelcomeService::class)->forUser(auth()->user()) : null);
@endphp

@if ($welcome)
    <div @class([
            'rounded-3xl overflow-hidden bg-gradient-to-br from-brand to-brand-light text-white shadow-lg ring-1 ring-brand/20',
            'mb-6' => ! $standalone,
        ])
         x-data="{
            i: 0,
            startX: 0,
            cards: {{ count($welcome['cards']) }},
            next() { this.i = Math.min(this.i + 1, this.cards - 1) },
            prev() { this.i = Math.max(this.i - 1, 0) },
            swipe(e) {
                const dx = e.changedTouches[0].clientX - this.startX;
                if (dx < -40) this.next();
                if (dx > 40) this.prev();
            }
         }">
        <div @class([
                'flex flex-col p-6 sm:p-8',
                'min-h-[28rem] sm:min-h-[30rem]' => ! $standalone,
                'min-h-[30rem] sm:min-h-[32rem] sm:p-10' => $standalone,
            ])
             @touchstart="startX = $event.changedTouches[0].clientX"
             @touchend="swipe($event)">
            {{-- Eyebrow zone (fixed) --}}
            <div class="flex items-center justify-between gap-3 shrink-0">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('account_welcome.kicker') }}</p>
                <form method="POST" action="{{ route('site.account-welcome.complete') }}">
                    @csrf
                    <input type="hidden" name="audience" value="{{ $welcome['audience'] }}">
                    <button type="submit" class="text-sm font-semibold text-white/80 hover:text-white">{{ __('account_welcome.skip') }}</button>
                </form>
            </div>

            {{-- Slide body: absolute slides inside fixed-height shell so cards never jump --}}
            <div class="relative flex-1 mt-4 min-h-[18rem] sm:min-h-[20rem]">
                @foreach ($welcome['cards'] as $index => $card)
                    <div x-show="i === {{ $index }}" @if ($index > 0) x-cloak @endif
                         class="absolute inset-0 flex flex-col items-center text-center px-2">
                        <p class="shrink-0 text-xs font-semibold uppercase tracking-widest text-white/70">
                            {{ __('account_welcome.card_of', ['current' => $index + 1, 'total' => count($welcome['cards'])]) }}
                        </p>

                        <div @class([
                                'kf-welcome-art shrink-0 h-28 w-full max-w-[11rem] flex items-center justify-center mt-4 mb-3',
                                'kf-welcome-art-rewards' => ($card['variant'] ?? '') === 'rewards',
                            ])>
                            @include('components.site.illustrations.product', ['type' => $card['illustration'] ?? 'wallet'])
                        </div>

                        @if (($card['variant'] ?? '') === 'rewards')
                            <p class="shrink-0 text-[11px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ __('borrower.rewards.eyebrow') }}</p>
                        @endif

                        <h2 @class([
                                'shrink-0 font-bold leading-tight mt-2 max-w-md min-h-[3.25rem] sm:min-h-[3.75rem] flex items-center justify-center',
                                'text-2xl sm:text-3xl' => ! $standalone,
                                'text-3xl sm:text-4xl min-h-[3.75rem] sm:min-h-[4.5rem]' => $standalone,
                            ])>{{ $card['title'] }}</h2>

                        <p @class([
                                'shrink-0 text-white/85 leading-relaxed mt-3 max-w-md min-h-[4.5rem] sm:min-h-[5.25rem]',
                                'text-sm sm:text-base' => ! $standalone,
                                'text-base sm:text-lg min-h-[5.25rem] sm:min-h-[6rem]' => $standalone,
                            ])>{{ $card['body'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Actions zone (fixed) --}}
            <div class="flex items-center justify-between gap-3 pt-2 shrink-0">
                <div class="flex items-center gap-1.5" role="tablist">
                    @foreach ($welcome['cards'] as $index => $card)
                        <button type="button" @click="i = {{ $index }}"
                                class="size-2 rounded-full"
                                :class="i === {{ $index }} ? 'bg-brand-gold scale-125' : 'bg-white/40'"
                                aria-label="{{ $card['title'] }}"></button>
                    @endforeach
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="prev()" x-show="i > 0" x-cloak class="rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold">{{ __('account_welcome.back') }}</button>
                    <button type="button" @click="next()" x-show="i < cards - 1" class="rounded-xl bg-brand-gold text-brand px-4 py-2 text-sm font-extrabold">{{ __('account_welcome.next') }}</button>
                    <form method="POST" action="{{ route('site.account-welcome.complete') }}" x-show="i === cards - 1" x-cloak>
                        @csrf
                        <input type="hidden" name="audience" value="{{ $welcome['audience'] }}">
                        <button type="submit" class="rounded-xl bg-brand-gold text-brand px-4 py-2 text-sm font-extrabold">{{ __('account_welcome.finish') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
