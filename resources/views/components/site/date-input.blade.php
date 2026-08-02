@props([
    'name',
    'label' => '',
    'value' => null,
    'required' => false,
    'min' => null,
    'max' => null,
    'help' => null,
    'inputClass' => 'w-full px-3.5 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-base outline-none transition',
])

@php
    $selected = old($name, $value);
    $selected = filled($selected) ? \Illuminate\Support\Str::of((string) $selected)->substr(0, 10)->toString() : '';
    $minDate = $min ?: '1940-01-01';
    $maxDate = $max ?: now()->format('Y-m-d');
    $id = 'date-'.str_replace(['[', ']'], ['-', ''], $name).'-'.substr(md5($name.$selected), 0, 6);
@endphp

<div
    x-data="{
        open: false,
        desktopOpen: false,
        value: @js($selected),
        draft: @js($selected ?: $maxDate),
        min: @js($minDate),
        max: @js($maxDate),
        months: ['January','February','March','April','May','June','July','August','September','October','November','December'],
        viewYear: 0,
        viewMonth: 0,
        init() {
            const base = this.parse(this.value || this.max);
            this.viewYear = base.getFullYear();
            this.viewMonth = base.getMonth();
            if (! this.value) this.draft = this.format(base);
        },
        parse(str) {
            const [y, m, d] = String(str || '').split('-').map(Number);
            if (! y || ! m || ! d) return new Date();
            return new Date(y, m - 1, d);
        },
        format(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        },
        display(str) {
            if (! str) return 'Select date';
            const date = this.parse(str);
            return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
        },
        clamp(str) {
            if (str < this.min) return this.min;
            if (str > this.max) return this.max;
            return str;
        },
        openSheet() {
            this.draft = this.clamp(this.value || this.max);
            const d = this.parse(this.draft);
            this.viewYear = d.getFullYear();
            this.viewMonth = d.getMonth();
            this.open = true;
        },
        openDesktop() {
            this.draft = this.clamp(this.value || this.max);
            const d = this.parse(this.draft);
            this.viewYear = d.getFullYear();
            this.viewMonth = d.getMonth();
            this.desktopOpen = true;
        },
        years() {
            const minY = this.parse(this.min).getFullYear();
            const maxY = this.parse(this.max).getFullYear();
            const list = [];
            for (let y = maxY; y >= minY; y--) list.push(y);
            return list;
        },
        daysInMonth(year, month) {
            return new Date(year, month + 1, 0).getDate();
        },
        firstWeekday(year, month) {
            return new Date(year, month, 1).getDay();
        },
        calendarDays() {
            const days = [];
            const blanks = this.firstWeekday(this.viewYear, this.viewMonth);
            for (let i = 0; i < blanks; i++) days.push(null);
            const total = this.daysInMonth(this.viewYear, this.viewMonth);
            for (let d = 1; d <= total; d++) {
                const str = this.format(new Date(this.viewYear, this.viewMonth, d));
                days.push({
                    day: d,
                    value: str,
                    disabled: str < this.min || str > this.max,
                    selected: str === this.draft,
                    today: str === this.format(new Date()),
                });
            }
            return days;
        },
        pickDay(day) {
            if (! day || day.disabled) return;
            this.draft = day.value;
        },
        confirm() {
            this.value = this.clamp(this.draft);
            this.open = false;
            this.desktopOpen = false;
        },
        clear() {
            this.value = '';
            this.open = false;
            this.desktopOpen = false;
        },
        shiftMonth(delta) {
            let m = this.viewMonth + delta;
            let y = this.viewYear;
            if (m < 0) { m = 11; y--; }
            if (m > 11) { m = 0; y++; }
            this.viewMonth = m;
            this.viewYear = y;
        },
    }"
    class="relative"
    @keydown.escape.window="open = false; desktopOpen = false"
>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}
            @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <input type="hidden" name="{{ $name }}" :value="value" @if ($required) required @endif>

    {{-- Mobile: bottom sheet --}}
    <div class="lg:hidden">
        <button type="button" id="{{ $id }}" @click="openSheet()"
                class="{{ $inputClass }} inline-flex items-center justify-between gap-3 text-left">
            <span class="truncate" :class="value ? 'text-gray-900' : 'text-gray-400'" x-text="display(value)"></span>
            <svg class="w-5 h-5 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
        </button>

        <x-site.bottom-sheet :title="$label ?: 'Select date'" open="open">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1">Month</label>
                        <select x-model.number="viewMonth" class="w-full rounded-xl border-gray-200 text-base px-3 py-2.5">
                            @foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $idx => $monthName)
                                <option value="{{ $idx }}">{{ $monthName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1">Year</label>
                        <select x-model.number="viewYear" class="w-full rounded-xl border-gray-200 text-base px-3 py-2.5">
                            @for ($y = (int) now()->format('Y'); $y >= 1940; $y--)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-7 gap-1 text-center text-[10px] uppercase tracking-wide text-gray-400 font-semibold">
                    <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                </div>
                <div class="grid grid-cols-7 gap-1">
                    <template x-for="(day, idx) in calendarDays()" :key="idx">
                        <button type="button"
                                @click="pickDay(day)"
                                :disabled="!day || day.disabled"
                                class="aspect-square rounded-xl text-sm font-semibold transition"
                                :class="!day ? 'invisible' : (day.selected ? 'bg-brand text-white' : (day.today ? 'ring-1 ring-brand text-brand' : (day.disabled ? 'text-gray-300' : 'hover:bg-brand-muted text-gray-800')))"
                                x-text="day ? day.day : ''"></button>
                    </template>
                </div>

                <p class="text-sm text-gray-600 text-center">Selected: <span class="font-semibold text-gray-900" x-text="display(draft)"></span></p>

                <div class="flex gap-2">
                    @unless ($required)
                        <button type="button" @click="clear()" class="flex-1 rounded-xl ring-1 ring-gray-200 py-3 text-sm font-semibold text-gray-600">Clear</button>
                    @endunless
                    <button type="button" @click="confirm()" class="flex-1 rounded-xl bg-brand hover:bg-brand-light text-white py-3 text-sm font-semibold">Confirm</button>
                </div>
            </div>
        </x-site.bottom-sheet>
    </div>

    {{-- Desktop: popover calendar --}}
    <div class="hidden lg:block relative">
        <button type="button" @click="openDesktop()"
                class="{{ $inputClass }} inline-flex items-center justify-between gap-3 text-left">
            <span class="truncate" :class="value ? 'text-gray-900' : 'text-gray-400'" x-text="display(value)"></span>
            <svg class="w-5 h-5 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
        </button>

        <div x-show="desktopOpen" x-cloak @click.outside="desktopOpen = false"
             class="absolute z-[80] mt-2 w-[22rem] rounded-2xl bg-white shadow-xl ring-1 ring-brand/15 p-4">
            <div class="flex items-center justify-between mb-3">
                <button type="button" @click="shiftMonth(-1)" class="p-2 rounded-lg hover:bg-gray-50 text-gray-600" aria-label="Previous month">‹</button>
                <div class="flex items-center gap-2">
                    <select x-model.number="viewMonth" class="rounded-lg border-gray-200 text-sm py-1.5">
                        @foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $idx => $monthName)
                            <option value="{{ $idx }}">{{ $monthName }}</option>
                        @endforeach
                    </select>
                    <select x-model.number="viewYear" class="rounded-lg border-gray-200 text-sm py-1.5">
                        @for ($y = (int) now()->format('Y'); $y >= 1940; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <button type="button" @click="shiftMonth(1)" class="p-2 rounded-lg hover:bg-gray-50 text-gray-600" aria-label="Next month">›</button>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-1">
                <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
            </div>
            <div class="grid grid-cols-7 gap-1 mb-3">
                <template x-for="(day, idx) in calendarDays()" :key="'d'+idx">
                    <button type="button"
                            @click="pickDay(day)"
                            :disabled="!day || day.disabled"
                            class="aspect-square rounded-xl text-sm font-semibold transition"
                            :class="!day ? 'invisible' : (day.selected ? 'bg-brand text-white' : (day.today ? 'ring-1 ring-brand text-brand' : (day.disabled ? 'text-gray-300' : 'hover:bg-brand-muted text-gray-800')))"
                            x-text="day ? day.day : ''"></button>
                </template>
            </div>
            <div class="flex gap-2">
                @unless ($required)
                    <button type="button" @click="clear()" class="flex-1 rounded-xl ring-1 ring-gray-200 py-2.5 text-sm font-semibold text-gray-600">Clear</button>
                @endunless
                <button type="button" @click="confirm()" class="flex-1 rounded-xl bg-brand hover:bg-brand-light text-white py-2.5 text-sm font-semibold">Apply</button>
            </div>
        </div>
    </div>

    @if ($help)
        <p class="mt-1.5 text-xs text-gray-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
