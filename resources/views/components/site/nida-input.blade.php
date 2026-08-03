@props([
    'name' => 'national_id',
    'value' => '',
    'required' => true,
    'readonly' => false,
    'placeholder' => 'XXXXXXXX-XXXXX-XXXXX-XX',
])

@php
    $displayValue = old($name, $value);
    $digits = preg_replace('/\D/', '', (string) $displayValue) ?? '';
    $isReadonly = filter_var($readonly, FILTER_VALIDATE_BOOLEAN) || $attributes->has('readonly');
@endphp

<div
    class="nida-boxes"
    x-data="nidaBoxes({
        name: @js($name),
        initial: @js($digits),
        required: @js((bool) $required),
        readonly: @js((bool) $isReadonly),
    })"
    x-init="init()"
>
    <input type="hidden" :name="name" :value="formatted" @if ($required) required @endif pattern="[0-9]{8}-[0-9]{5}-[0-9]{5}-[0-9]{2}">
    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2" role="group" aria-label="{{ __('borrower.nida.number') }}">
        <template x-for="(group, gi) in groups" :key="'g'+gi">
            <span class="inline-flex items-center gap-1.5 sm:gap-2">
                <input
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    autocorrect="off"
                    spellcheck="false"
                    class="kf-field font-mono tracking-[0.18em] text-center tabular-nums"
                    :class="gi === 0 ? 'w-[9.5rem] sm:w-[11rem]' : (gi === 3 ? 'w-[3.25rem]' : 'w-[6.5rem] sm:w-[7rem]')"
                    :maxlength="group.len"
                    :value="group.value"
                    :readonly="readonly"
                    :aria-label="@js(__('borrower.nida.number')) + ' ' + (gi + 1)"
                    x-on:input="onGroupInput(gi, $event)"
                    x-on:keydown="onGroupKeydown(gi, $event)"
                    x-on:paste.prevent="onPaste($event)"
                >
                <span x-show="gi < groups.length - 1" class="text-lg font-bold text-gray-400 select-none" aria-hidden="true">-</span>
            </span>
        </template>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('nidaBoxes', (config) => ({
                    name: config.name,
                    required: !!config.required,
                    readonly: !!config.readonly,
                    digits: String(config.initial || '').replace(/\D/g, '').slice(0, 20),
                    groups: [
                        { len: 8, value: '' },
                        { len: 5, value: '' },
                        { len: 5, value: '' },
                        { len: 2, value: '' },
                    ],
                    get formatted() {
                        const d = this.digits;
                        if (d.length <= 8) return d;
                        if (d.length <= 13) return d.slice(0, 8) + '-' + d.slice(8);
                        if (d.length <= 18) return d.slice(0, 8) + '-' + d.slice(8, 13) + '-' + d.slice(13);
                        return d.slice(0, 8) + '-' + d.slice(8, 13) + '-' + d.slice(13, 18) + '-' + d.slice(18);
                    },
                    init() {
                        this.syncGroups();
                    },
                    syncGroups() {
                        const d = this.digits;
                        this.groups[0].value = d.slice(0, 8);
                        this.groups[1].value = d.slice(8, 13);
                        this.groups[2].value = d.slice(13, 18);
                        this.groups[3].value = d.slice(18, 20);
                    },
                    rebuildFromGroups() {
                        this.digits = this.groups.map(g => String(g.value || '').replace(/\D/g, '')).join('').slice(0, 20);
                        this.syncGroups();
                    },
                    onGroupInput(gi, event) {
                        if (this.readonly) return;
                        const raw = String(event.target.value || '').replace(/\D/g, '');
                        this.groups[gi].value = raw.slice(0, this.groups[gi].len);
                        event.target.value = this.groups[gi].value;
                        this.rebuildFromGroups();
                        if (raw.length >= this.groups[gi].len && gi < 3) {
                            this.focusBox(gi + 1);
                        }
                    },
                    onGroupKeydown(gi, event) {
                        if (event.key === 'Backspace' && !event.target.value && gi > 0) {
                            this.focusBox(gi - 1);
                        }
                    },
                    onPaste(event) {
                        if (this.readonly) return;
                        const pasted = (event.clipboardData || window.clipboardData).getData('text') || '';
                        this.digits = String(pasted).replace(/\D/g, '').slice(0, 20);
                        this.syncGroups();
                        const focusIdx = Math.min(3, Math.floor(this.digits.length / 8) || 0);
                        this.$nextTick(() => this.focusBox(Math.min(3, this.digits.length >= 18 ? 3 : (this.digits.length >= 13 ? 2 : (this.digits.length >= 8 ? 1 : 0)))));
                    },
                    focusBox(gi) {
                        const boxes = this.$root.querySelectorAll('input[type="text"]');
                        if (boxes[gi]) boxes[gi].focus();
                    },
                }));
            });
        </script>
    @endpush
@endonce
