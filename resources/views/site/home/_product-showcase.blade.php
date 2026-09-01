{{-- Desktop only. Mobile must not show these demo screens. --}}
<div
    class="mt-8 lg:mt-0"
    x-data="{
        i: 0,
        n: 4,
        paused: false,
        next() { this.i = (this.i + 1) % this.n },
        go(k) { this.i = k; this.paused = true },
    }"
    x-init="
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!reduce) {
            setInterval(() => { if (!this.paused) this.next() }, 5000);
        }
    "
>
    <div class="hidden lg:block">
        <x-site.device-frame>
            <div class="relative h-full">
                <div x-show="i === 0" class="h-full">@include('site.home._screen-home')</div>
                <div x-show="i === 1" x-cloak class="h-full">@include('site.home._screen-loans')</div>
                <div x-show="i === 2" x-cloak class="h-full">@include('site.home._screen-market')</div>
                <div x-show="i === 3" x-cloak class="h-full">@include('site._plus-phone-screen', [
                    'title' => __('plus.home.summary'),
                    'body' => __('site.plus.screen_home_body'),
                    'stats' => [
                        ['label' => __('plus.money.left_label'), 'value' => '730K', 'gold' => true],
                        ['label' => __('plus.business.diff'), 'value' => '+1.15M'],
                        ['label' => __('plus.reports.trust'), 'value' => '81'],
                    ],
                    'bar' => 72,
                    'note' => __('site.plus.screen_home_note'),
                ])</div>
            </div>
        </x-site.device-frame>
        <div class="mt-4 flex justify-center gap-2">
            @foreach ([__('site.hero.showcase_home'), __('site.hero.showcase_loans'), __('site.hero.showcase_market'), __('site.hero.showcase_plus')] as $idx => $label)
                <button type="button" @click="go({{ $idx }})" class="size-2 rounded-full" :class="i === {{ $idx }} ? 'bg-brand' : 'bg-gray-300'" aria-label="{{ $label }}"></button>
            @endforeach
        </div>
    </div>
</div>
