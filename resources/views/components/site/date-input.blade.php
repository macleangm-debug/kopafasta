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
    $monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $minYear = (int) substr($minDate, 0, 4);
    $maxYear = (int) substr($maxDate, 0, 4);
@endphp

<div
    x-data="{
        open: false,
        desktopOpen: false,
        desktopStyle: '',
        pickerMode: 'calendar',
        finePointer: typeof window !== 'undefined' && window.matchMedia('(hover: hover) and (pointer: fine)').matches,
        value: @js($selected),
        draft: @js($selected ?: $maxDate),
        min: @js($minDate),
        max: @js($maxDate),
        months: @js($monthNames),
        viewYear: 0,
        viewMonth: 0,
        init() {
            const base = this.parse(this.value || this.max);
            this.viewYear = base.getFullYear();
            this.viewMonth = base.getMonth();
            if (! this.value) this.draft = this.format(base);
        },
        openPicker() {
            if (this.finePointer) {
                this.openDesktop();
                this.positionDesktop();
            } else {
                this.openSheet();
            }
        },
        positionDesktop() {
            this.$nextTick(() => {
                const btn = this.$refs.triggerBtn || this.$el.querySelector('[data-date-trigger]');
                if (! btn) return;
                const r = btn.getBoundingClientRect();
                const panelW = 352;
                const panelH = 380;
                const left = Math.max(12, Math.min(r.left, window.innerWidth - panelW - 12));
                let top = r.bottom + 8;
                if (top + panelH > window.innerHeight - 12) {
                    top = Math.max(12, r.top - panelH - 8);
                }
                this.desktopStyle = `left:${left}px;top:${top}px;`;
            });
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
            if (! str) return @js(__('borrower.register.dob_select'));
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
            this.pickerMode = 'calendar';
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
        pickMonth(idx) {
            this.viewMonth = idx;
            this.pickerMode = 'calendar';
        },
        pickYear(y) {
            this.viewYear = y;
            this.pickerMode = 'calendar';
        },
        confirm() {
            this.value = this.clamp(this.draft);
            this.open = false;
            this.desktopOpen = false;
            this.pickerMode = 'calendar';
        },
        clear() {
            this.value = '';
            this.open = false;
            this.desktopOpen = false;
            this.pickerMode = 'calendar';
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
    @keydown.escape.window="open = false; desktopOpen = false; pickerMode = 'calendar'"
>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}
            @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <input type="hidden" name="{{ $name }}" :value="value" @if ($required) required @endif>

    <button type="button" id="{{ $id }}" data-date-trigger x-ref="triggerBtn" @click="openPicker()"
            class="{{ $inputClass }} inline-flex items-center justify-between gap-3 text-left">
        <span class="truncate" :class="value ? 'text-gray-900' : 'text-gray-400'" x-text="display(value)"></span>
        <svg class="w-5 h-5 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
    </button>

    {{-- Touch / mobile: bottom sheet (always in DOM; only opened when !finePointer) --}}
    <x-site.bottom-sheet :title="$label ?: 'Select date'" open="open">
        <div class="space-y-4">
            <template x-if="pickerMode === 'calendar'">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="pickerMode = 'month'"
                                class="rounded-xl border border-gray-200 px-3 py-3 text-left hover:bg-gray-50">
                            <span class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-0.5">Month</span>
                            <span class="text-sm font-semibold text-gray-900" x-text="months[viewMonth]"></span>
                        </button>
                        <button type="button" @click="pickerMode = 'year'"
                                class="rounded-xl border border-gray-200 px-3 py-3 text-left hover:bg-gray-50">
                            <span class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-0.5">Year</span>
                            <span class="text-sm font-semibold text-gray-900" x-text="viewYear"></span>
                        </button>
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
            </template>

            <template x-if="pickerMode === 'month'">
                <div>
                    <button type="button" @click="pickerMode = 'calendar'" class="mb-3 text-sm font-semibold text-brand">← Back to calendar</button>
                    <div class="max-h-[55vh] overflow-y-auto space-y-1">
                        <template x-for="(month, idx) in months" :key="'m'+idx">
                            <button type="button"
                                    @click="pickMonth(idx)"
                                    class="w-full text-left px-4 py-3.5 rounded-xl text-sm font-medium transition"
                                    :class="viewMonth === idx ? 'bg-brand-muted text-brand ring-1 ring-brand/20 font-semibold' : 'text-gray-800 hover:bg-gray-50'"
                                    x-text="month"></button>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="pickerMode === 'year'">
                <div>
                    <button type="button" @click="pickerMode = 'calendar'" class="mb-3 text-sm font-semibold text-brand">← Back to calendar</button>
                    <div class="max-h-[55vh] overflow-y-auto space-y-1">
                        <template x-for="y in years()" :key="'y'+y">
                            <button type="button"
                                    @click="pickYear(y)"
                                    class="w-full text-left px-4 py-3.5 rounded-xl text-sm font-medium transition"
                                    :class="viewYear === y ? 'bg-brand-muted text-brand ring-1 ring-brand/20 font-semibold' : 'text-gray-800 hover:bg-gray-50'"
                                    x-text="y"></button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </x-site.bottom-sheet>

    {{-- Fine pointer (desktop): teleport so glass-card backdrop-filter cannot clip the calendar --}}
    <template x-teleport="body">
        <div x-show="finePointer && desktopOpen" x-cloak @click.outside="desktopOpen = false"
             class="fixed z-[90] w-[22rem] rounded-2xl bg-white shadow-xl ring-1 ring-brand/15 p-4"
             x-ref="desktopCal"
             :style="desktopStyle"
             x-init="$watch('desktopOpen', v => { if (v) positionDesktop(); })">
            <div class="flex items-center justify-between mb-3">
                <button type="button" @click="shiftMonth(-1)" class="p-2 rounded-lg hover:bg-gray-50 text-gray-600" aria-label="Previous month">‹</button>
                <div class="flex items-center gap-2">
                    <select x-model.number="viewMonth" class="rounded-lg border-gray-200 text-sm py-1.5">
                        @foreach ($monthNames as $idx => $monthName)
                            <option value="{{ $idx }}">{{ $monthName }}</option>
                        @endforeach
                    </select>
                    <select x-model.number="viewYear" class="rounded-lg border-gray-200 text-sm py-1.5">
                        @for ($y = $maxYear; $y >= $minYear; $y--)
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
    </template>

    @if ($help)
        <p class="mt-1.5 text-xs text-gray-500">{{ $help }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
