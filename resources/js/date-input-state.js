import {
    clampDate,
    confirmDraft,
    daysInMonth,
    draftFromView,
    formatYmd,
    openAnchorDate,
    parseYmd,
    prepareOpenState,
} from './date-input-draft';

function displayPlaceholder() {
    return 'Select date';
}

/**
 * Alpine factory for x-site.date-input.
 * Config: { name, triggerId, value, fallback, min, max, months, selectLabel, required }
 */
export function createDateInputState(config = {}) {
    const months = config.months || [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    return {
        open: false,
        desktopOpen: false,
        desktopStyle: '',
        pickerMode: 'calendar',
        narrow: typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches,
        fieldName: config.name || '',
        triggerId: config.triggerId || '',
        value: config.value || '',
        draft: config.value || '',
        fallback: config.fallback || '',
        min: config.min || '',
        max: config.max || '',
        months,
        selectLabel: config.selectLabel || displayPlaceholder(),
        required: !! config.required,
        viewYear: 0,
        viewMonth: 0,

        init() {
            this.narrow = this.isNarrow();
            const today = this.format(new Date());
            const open = prepareOpenState({
                value: this.value,
                fallback: this.fallback,
                min: this.min,
                max: this.max,
                today,
            });
            this.viewYear = open.viewYear;
            this.viewMonth = open.viewMonth;
            if (! this.value) {
                this.draft = '';
            }
        },

        resolveHidden() {
            const btn = typeof document !== 'undefined' ? document.getElementById(this.triggerId) : null;
            const root = btn?.closest('.relative') || btn?.parentElement;
            const match = (node) => {
                if (! node) {
                    return null;
                }
                for (const input of node.querySelectorAll('input[type=hidden]')) {
                    if (input.name === this.fieldName) {
                        return input;
                    }
                }

                return null;
            };

            return match(root)
                || (typeof document !== 'undefined' ? match(document) : null);
        },

        emitDateChanged(next) {
            const name = this.fieldName || '';
            const value = next == null ? '' : String(next);
            const hidden = this.resolveHidden();
            if (hidden) {
                hidden.value = value;
                hidden.setAttribute('value', value);
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const from = hidden
                || (typeof document !== 'undefined' ? document.getElementById(this.triggerId) : null)
                || document;
            from.dispatchEvent(new CustomEvent('kf-date-changed', {
                bubbles: true,
                composed: true,
                detail: { name, value },
            }));
        },

        isNarrow() {
            return typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches;
        },

        openPicker() {
            this.narrow = this.isNarrow();
            if (this.narrow) {
                this.openSheet();
            } else {
                this.openDesktop();
                this.positionDesktop();
            }
        },

        positionDesktop() {
            this.$nextTick(() => {
                const btn = this.$refs.triggerBtn || this.$el.querySelector('[data-date-trigger]');
                if (! btn) {
                    return;
                }
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
            const parsed = parseYmd(str);
            if (! parsed) {
                return new Date();
            }

            return new Date(parsed.year, parsed.monthIndex, parsed.day);
        },

        format(date) {
            return formatYmd(date.getFullYear(), date.getMonth(), date.getDate());
        },

        display(str) {
            if (! str) {
                return this.selectLabel;
            }
            const date = this.parse(str);

            return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
        },

        clamp(str) {
            return clampDate(str, this.min, this.max);
        },

        openAnchor() {
            return openAnchorDate({
                fallback: this.fallback,
                min: this.min,
                max: this.max,
                today: this.format(new Date()),
            });
        },

        prepareOpen() {
            const open = prepareOpenState({
                value: this.value,
                fallback: this.fallback,
                min: this.min,
                max: this.max,
                today: this.format(new Date()),
            });
            // Critical: draft is only the committed/in-progress selection — never openAnchor.
            this.draft = open.draft;
            this.viewYear = open.viewYear;
            this.viewMonth = open.viewMonth;
            this.pickerMode = 'calendar';
        },

        openSheet() {
            this.prepareOpen();
            this.open = true;
        },

        openDesktop() {
            this.prepareOpen();
            this.desktopOpen = true;
        },

        years() {
            const maxY = this.max ? (parseYmd(this.max)?.year || new Date().getFullYear()) : new Date().getFullYear();
            const minY = this.min ? (parseYmd(this.min)?.year || (maxY - 120)) : (maxY - 120);
            const list = [];
            for (let y = maxY; y >= minY; y--) {
                list.push(y);
            }

            return list;
        },

        daysInMonth(year, month) {
            return daysInMonth(year, month);
        },

        firstWeekday(year, month) {
            return new Date(year, month, 1).getDay();
        },

        calendarDays() {
            const days = [];
            const blanks = this.firstWeekday(this.viewYear, this.viewMonth);
            for (let i = 0; i < blanks; i++) {
                days.push(null);
            }
            const total = this.daysInMonth(this.viewYear, this.viewMonth);
            for (let d = 1; d <= total; d++) {
                const str = this.format(new Date(this.viewYear, this.viewMonth, d));
                days.push({
                    day: d,
                    value: str,
                    disabled: (this.min && str < this.min) || (this.max && str > this.max),
                    selected: !! this.draft && str === this.draft,
                    today: str === this.format(new Date()),
                });
            }

            return days;
        },

        syncDraftFromView() {
            this.draft = draftFromView({
                viewYear: this.viewYear,
                viewMonth: this.viewMonth,
                draft: this.draft,
                value: this.value,
                fallback: this.fallback,
                min: this.min,
                max: this.max,
                today: this.format(new Date()),
            });
        },

        pickDay(day) {
            if (! day || day.disabled) {
                return;
            }
            this.draft = day.value;
        },

        pickMonth(idx) {
            this.viewMonth = idx;
            this.syncDraftFromView();
            this.pickerMode = 'calendar';
        },

        pickYear(y) {
            this.viewYear = y;
            this.syncDraftFromView();
            this.pickerMode = 'calendar';
        },

        confirm() {
            const next = confirmDraft(this.draft, this.min, this.max);
            if (next == null) {
                // No selection yet — keep picker open; never commit openAnchor.
                return;
            }
            this.value = next;
            this.open = false;
            this.desktopOpen = false;
            this.pickerMode = 'calendar';
            this.$nextTick(() => {
                this.emitDateChanged(this.value || '');
            });
        },

        clear() {
            this.value = '';
            this.draft = '';
            this.open = false;
            this.desktopOpen = false;
            this.pickerMode = 'calendar';
            this.$nextTick(() => {
                this.emitDateChanged('');
            });
        },

        shiftMonth(delta) {
            let m = this.viewMonth + delta;
            let y = this.viewYear;
            if (m < 0) {
                m = 11;
                y--;
            }
            if (m > 11) {
                m = 0;
                y++;
            }
            this.viewMonth = m;
            this.viewYear = y;
            // Keep an existing in-progress draft aligned with the month being viewed.
            if (this.draft) {
                this.syncDraftFromView();
            }
        },
    };
}

export function registerDateInput(Alpine) {
    Alpine.data('kfDateInput', (config = {}) => createDateInputState(config));
}
