@props([
    'name',
    'id' => null,
    'label' => null,
    'required' => false,
    'value' => null,
])

@php
    $inputId = $id ?: $name;
@endphp

<div class="rounded-2xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3"
     x-data="{
        spoken: '',
        formatted: '',
        sync(val) {
            this.formatted = val || '';
            const n = Number(String(val || '').replace(/[^\d.-]/g, '')) || 0;
            if (n < 1000) { this.spoken = ''; return; }
            const loc = document.documentElement.lang === 'sw' ? 'sw' : 'en';
            let words = '';
            if (n < 1000000) {
                const u = n / 1000;
                const t = u >= 10 ? Math.round(u) : Math.round(u * 100) / 100;
                words = loc === 'sw' ? ('elfu ' + t) : (t + ' thousand');
            } else if (n < 1000000000) {
                const u = n / 1000000;
                const t = u >= 10 ? Math.round(u) : Math.round(u * 100) / 100;
                words = loc === 'sw' ? ('milioni ' + t) : (t + ' million');
            } else {
                const u = n / 1000000000;
                const t = u >= 10 ? Math.round(u) : Math.round(u * 100) / 100;
                words = loc === 'sw' ? ('bilioni ' + t) : (t + ' billion');
            }
            this.spoken = words;
        }
     }">
    @if ($label)
        <p class="text-xs font-medium text-gray-600">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</p>
    @endif
    <div class="mt-1 flex items-baseline gap-2">
        <span class="text-lg font-bold text-brand shrink-0">{{ currency_code() }}</span>
        <x-site.numeric-input
            :name="$name"
            :id="$inputId"
            :money="true"
            :required="$required"
            :value="$value"
            class="flex-1 bg-transparent border-0 ring-0 focus:ring-0 px-0 py-1 text-3xl font-bold text-gray-900 tabular-nums"
            @input="sync($event.target.value)"
        />
    </div>
    <p class="mt-1 text-xs text-gray-500 min-h-4" x-text="spoken" x-cloak></p>
</div>
