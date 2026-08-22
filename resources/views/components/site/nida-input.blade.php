@props([
    'name' => 'national_id',
    'value' => '',
    'required' => true,
    'readonly' => false,
    'placeholder' => null,
    'groups' => null,
    'country' => null,
])

@php
    $countryCode = $country ?: app(\App\Services\CountrySettingsService::class)->defaultCountryCode();
    $groupLens = is_array($groups) && $groups !== []
        ? array_values(array_map('intval', $groups))
        : \App\Support\NationalIdValidator::groups($countryCode);
    $displayValue = old($name, $value);
    $digits = preg_replace('/\D/', '', (string) $displayValue) ?? '';
    $isReadonly = filter_var($readonly, FILTER_VALIDATE_BOOLEAN) || $attributes->has('readonly');
    $placeholder = $placeholder ?: \App\Support\NationalIdValidator::placeholder($countryCode);
@endphp

<div
    class="nida-boxes"
    x-data="nidaBoxes({
        name: @js($name),
        initial: @js($digits),
        required: @js((bool) $required),
        readonly: @js((bool) $isReadonly),
        groups: @js($groupLens),
    })"
    x-init="init()"
>
    <input type="hidden" :name="name" :value="formatted" @if ($required) required @endif>
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
                    :style="'width:' + Math.max(3.25, group.len * 0.85 + 1.4) + 'rem'"
                    :maxlength="group.len"
                    :value="group.value"
                    :readonly="readonly"
                    :placeholder="'X'.repeat(group.len)"
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
                    groupLens: (config.groups && config.groups.length ? config.groups : [8, 5, 5, 2]).map((n) => parseInt(n, 10)).filter((n) => n > 0),
                    digits: String(config.initial || '').replace(/\D/g, ''),
                    groups: [],
                    get maxLen() {
                        return this.groupLens.reduce((sum, n) => sum + n, 0);
                    },
                    get formatted() {
                        const d = this.digits;
                        let pos = 0;
                        const parts = [];
                        for (let i = 0; i < this.groupLens.length; i++) {
                            const len = this.groupLens[i];
                            const slice = d.slice(pos, pos + len);
                            if (slice === '') break;
                            parts.push(slice);
                            pos += len;
                            if (d.length <= pos) break;
                        }
                        return parts.join('-');
                    },
                    init() {
                        this.digits = this.digits.slice(0, this.maxLen);
                        this.groups = this.groupLens.map((len) => ({ len, value: '' }));
                        this.syncGroups();
                    },
                    syncGroups() {
                        let pos = 0;
                        this.groups = this.groupLens.map((len) => {
                            const value = this.digits.slice(pos, pos + len);
                            pos += len;
                            return { len, value };
                        });
                    },
                    rebuildFromGroups() {
                        this.digits = this.groups.map((g) => String(g.value || '').replace(/\D/g, '')).join('').slice(0, this.maxLen);
                        this.syncGroups();
                    },
                    onGroupInput(gi, event) {
                        if (this.readonly) return;
                        const raw = String(event.target.value || '').replace(/\D/g, '');
                        this.groups[gi].value = raw.slice(0, this.groups[gi].len);
                        event.target.value = this.groups[gi].value;
                        this.rebuildFromGroups();
                        if (raw.length >= this.groups[gi].len && gi < this.groups.length - 1) {
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
                        this.digits = String(pasted).replace(/\D/g, '').slice(0, this.maxLen);
                        this.syncGroups();
                        this.$nextTick(() => {
                            const filled = this.digits.length;
                            let pos = 0;
                            let focusIdx = 0;
                            for (let i = 0; i < this.groupLens.length; i++) {
                                pos += this.groupLens[i];
                                focusIdx = i;
                                if (filled < pos) break;
                            }
                            this.focusBox(Math.min(this.groups.length - 1, focusIdx));
                        });
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
