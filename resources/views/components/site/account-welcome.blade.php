@php
    $welcome = auth()->user() ? app(\App\Services\AccountWelcomeService::class)->forUser(auth()->user()) : null;
@endphp

@if ($welcome)
    <div class="mb-6 rounded-3xl overflow-hidden bg-gradient-to-br from-brand to-brand-light text-white shadow-lg ring-1 ring-brand/20"
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
        <div class="p-6 sm:p-8 space-y-5"
             @touchstart="startX = $event.changedTouches[0].clientX"
             @touchend="swipe($event)">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('account_welcome.kicker') }}</p>
                <form method="POST" action="{{ route('site.account-welcome.complete') }}">
                    @csrf
                    <input type="hidden" name="audience" value="{{ $welcome['audience'] }}">
                    <button type="submit" class="text-sm font-semibold text-white/80 hover:text-white">{{ __('account_welcome.skip') }}</button>
                </form>
            </div>

            @foreach ($welcome['cards'] as $index => $card)
                <div x-show="i === {{ $index }}" @if ($index > 0) x-cloak @endif class="space-y-2 min-h-[7.5rem]">
                    <p class="text-xs font-semibold uppercase tracking-widest text-white/70">{{ __('account_welcome.card_of', ['current' => $index + 1, 'total' => count($welcome['cards'])]) }}</p>
                    <h2 class="text-2xl sm:text-3xl font-bold leading-tight">{{ $card['title'] }}</h2>
                    <p class="text-sm sm:text-base text-white/85 leading-relaxed">{{ $card['body'] }}</p>
                </div>
            @endforeach

            <div class="flex items-center justify-between gap-3 pt-2">
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
                        <button type="submit" class="rounded-xl bg-brand-gold text-brand px-4 py-2 text-sm font-extrabold">{{ __('account_welcome.get_started') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
