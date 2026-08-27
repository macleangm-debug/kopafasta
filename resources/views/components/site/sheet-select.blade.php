@props([
    'name',
    'label',
    'options' => [],
    'value' => '',
    'required' => true,
])

@php
    $placeholder = __('site.partner_portal.valuation_choose');
@endphp

<div
    x-data="{
        open: false,
        value: @js((string) $value),
        labels: @js($options),
        placeholder: @js($placeholder),
        pick(code) { this.value = code; this.open = false; },
        get shown() { return (this.value && this.labels[this.value]) ? this.labels[this.value] : this.placeholder; },
    }"
    class="space-y-1.5"
>
    <p class="text-sm font-extrabold text-gray-900">{{ $label }}</p>
    <input type="hidden" name="{{ $name }}" x-model="value" @if ($required) required @endif>

    <div class="relative hidden lg:block">
        <button type="button" @click="open = !open"
                class="w-full text-left rounded-xl ring-1 ring-brand/15 bg-white px-4 py-3 text-sm font-semibold flex items-center justify-between gap-3">
            <span x-text="shown"></span>
            <span class="text-gray-400 shrink-0" aria-hidden="true">▾</span>
        </button>
        <div x-show="open" x-cloak @click.outside="open = false"
             class="absolute z-30 mt-1 w-full rounded-xl bg-white ring-1 ring-gray-200 shadow-lg overflow-hidden max-h-72 overflow-y-auto">
            @foreach ($options as $code => $text)
                <button type="button" @click="pick(@js((string) $code))"
                        class="w-full text-left px-4 py-3 text-sm hover:bg-brand-muted/40"
                        :class="value === @js((string) $code) ? 'bg-brand-muted font-bold text-brand' : 'text-gray-800'">
                    {{ $text }}
                </button>
            @endforeach
        </div>
    </div>

    <button type="button" @click="open = true"
            class="lg:hidden w-full text-left rounded-xl ring-1 ring-brand/15 bg-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between gap-3">
        <span x-text="shown"></span>
        <span class="text-gray-400 shrink-0" aria-hidden="true">▾</span>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="lg:hidden fixed inset-0 z-[80]" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
            <div class="absolute inset-x-0 bottom-0 bg-white rounded-t-2xl p-5 space-y-2 shadow-[0_-8px_40px_rgba(0,0,0,0.18)]"
                 style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                <div class="flex justify-center pb-1">
                    <div class="w-10 h-1 rounded-full bg-gray-300"></div>
                </div>
                <p class="font-extrabold text-gray-900">{{ $label }}</p>
                @foreach ($options as $code => $text)
                    <button type="button" @click="pick(@js((string) $code))"
                            class="w-full text-left rounded-xl ring-1 ring-gray-200 px-4 py-3.5 text-sm font-semibold"
                            :class="value === @js((string) $code) ? 'ring-brand bg-brand-muted/40 text-brand' : 'text-gray-800'">
                        {{ $text }}
                    </button>
                @endforeach
            </div>
        </div>
    </template>
</div>
