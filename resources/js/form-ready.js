/**
 * Shared "required fields filled" checks for Save / Continue gating.
 * Only counts [required] controls that are currently visible (or required hidden
 * fields whose nearest step panel is visible).
 */
function isElementVisible(el) {
    if (! el || !(el instanceof Element)) {
        return false;
    }

    if (el.type === 'hidden') {
        let node = el.parentElement;
        while (node && node !== document.body) {
            const style = window.getComputedStyle(node);
            if (style.display === 'none' || style.visibility === 'hidden') {
                return false;
            }
            node = node.parentElement;
        }

        return true;
    }

    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
}

function fieldFilled(el, form) {
    if (! el) {
        return false;
    }

    if (el.type === 'radio') {
        const name = el.name;
        if (! name || ! form) {
            return el.checked;
        }

        return [...form.querySelectorAll(`input[type="radio"][name="${CSS.escape(name)}"]`)].some((r) => r.checked);
    }

    if (el.type === 'checkbox') {
        return el.checked;
    }

    if (el.type === 'file') {
        return !!(el.files && el.files.length > 0);
    }

    if (el.tagName === 'SELECT') {
        return String(el.value || '').trim() !== '';
    }

    return String(el.value || '').trim() !== '';
}

export function isFormComplete(root, options = {}) {
    if (! root) {
        return false;
    }

    const form = root.matches?.('form') ? root : (root.querySelector?.('form') || root);
    const scope = options.scope
        ? (typeof options.scope === 'string' ? (form.querySelector(options.scope) || document.querySelector(options.scope)) : options.scope)
        : form;

    if (! scope) {
        return false;
    }

    const required = [...scope.querySelectorAll('[required]')].filter((el) => {
        if (el.disabled) {
            return false;
        }
        if (el.type === 'submit' || el.type === 'button' || el.type === 'reset' || el.type === 'image') {
            return false;
        }
        if (options.onlyVisible !== false && ! isElementVisible(el)) {
            return false;
        }

        return true;
    });

    if (required.length === 0) {
        // No required markers in scope — treat as incomplete for gated CTAs
        // unless explicitly allowed.
        return options.allowEmpty === true;
    }

    const seenRadios = new Set();

    for (const el of required) {
        if (el.type === 'radio') {
            if (seenRadios.has(el.name)) {
                continue;
            }
            seenRadios.add(el.name);
        }

        if (! fieldFilled(el, form)) {
            return false;
        }
    }

    return true;
}

export function registerFormReadyAlpine(Alpine) {
    window.KopaFastaForm = { isComplete: isFormComplete };

    Alpine.data('kfFormGate', (opts = {}) => ({
        ready: false,
        scope: opts.scope || null,
        allowEmpty: !!opts.allowEmpty,
        _timer: null,
        _onChange: null,
        init() {
            this.refresh();
            const form = this.$el.matches('form') ? this.$el : this.$el.closest('form');
            if (! form) {
                return;
            }
            this._onChange = () => this.refresh();
            form.addEventListener('input', this._onChange);
            form.addEventListener('change', this._onChange);
            this._timer = setInterval(() => this.refresh(), 400);
        },
        destroy() {
            const form = this.$el?.matches?.('form') ? this.$el : this.$el?.closest?.('form');
            if (form && this._onChange) {
                form.removeEventListener('input', this._onChange);
                form.removeEventListener('change', this._onChange);
            }
            if (this._timer) {
                clearInterval(this._timer);
            }
        },
        refresh() {
            const form = this.$el.matches('form') ? this.$el : this.$el.closest('form') || this.$el;
            this.ready = isFormComplete(form, {
                onlyVisible: true,
                scope: this.scope,
                allowEmpty: this.allowEmpty,
            });
        },
    }));

    Alpine.data('kfGatedSubmit', (opts = {}) => ({
        ready: false,
        scope: opts.scope || null,
        allowEmpty: !!opts.allowEmpty,
        _timer: null,
        _onChange: null,
        init() {
            const form = this.$el.closest('form');
            if (! form) {
                this.ready = this.allowEmpty;
                return;
            }
            this._onChange = () => {
                this.ready = isFormComplete(form, {
                    onlyVisible: true,
                    scope: this.scope,
                    allowEmpty: this.allowEmpty,
                });
            };
            this._onChange();
            form.addEventListener('input', this._onChange);
            form.addEventListener('change', this._onChange);
            this._timer = setInterval(this._onChange, 400);
        },
        destroy() {
            const form = this.$el?.closest?.('form');
            if (form && this._onChange) {
                form.removeEventListener('input', this._onChange);
                form.removeEventListener('change', this._onChange);
            }
            if (this._timer) {
                clearInterval(this._timer);
            }
        },
    }));
}
