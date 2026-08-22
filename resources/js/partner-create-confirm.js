export function registerPartnerCreateConfirm(Alpine) {
    Alpine.data('partnerCreateConfirm', (categoryLabels = {}) => ({
        open: false,
        form: null,
        mode: 'invite',
        notify: false,
        pin: '',
        pinConfirm: '',
        formError: '',
        summary: { name: '', category: '', entity: '', phone: '', email: '' },
        categoryLabels,

        fieldValue(form, name) {
            if (! form) {
                return '';
            }
            const fields = [...form.querySelectorAll(`[name="${CSS.escape(name)}"]`)];
            if (fields.length === 0) {
                return '';
            }
            if (fields[0].type === 'radio') {
                const checked = fields.find((field) => field.checked);

                return (checked?.value || '').trim();
            }

            return (fields[0].value || '').trim();
        },

        syncPhone(form) {
            const wrap = form?.querySelector('[data-phone-input]');
            const hidden = form?.querySelector('input[name="phone"]');
            if (! wrap || ! hidden) {
                return (hidden?.value || '').trim();
            }
            const local = wrap.querySelector('input[type="tel"]')?.value || '';
            const prefix = wrap.querySelector('select')?.value || '';
            const digits = prefix.replace(/\D/g, '') + local.replace(/\D/g, '').replace(/^0+/, '');
            hidden.value = digits;

            return digits.trim();
        },

        refreshSummary() {
            if (! this.form) {
                return;
            }
            const category = this.fieldValue(this.form, 'category');
            const applicant = this.fieldValue(this.form, 'applicant_category');
            const personType = ['valuer', 'affiliate'].includes(category);
            this.summary = {
                name: this.fieldValue(this.form, 'name') || 'New partner',
                category: this.categoryLabels[category] || category || 'Partner',
                entity: personType ? (applicant === 'individual' ? 'Individual' : 'Company') : '',
                phone: this.syncPhone(this.form) || '—',
                email: this.fieldValue(this.form, 'email') || '—',
            };
        },

        openFor(form) {
            if (! form) {
                return;
            }
            this.form = form;
            this.formError = '';
            this.refreshSummary();
            this.mode = 'invite';
            this.notify = false;
            this.pin = '';
            this.pinConfirm = '';
            this.open = true;
        },

        cancel() {
            this.open = false;
            this.formError = '';
            this.form = null;
        },

        activeFields(form) {
            const wizard = form.querySelector('.admin-wizard');
            const scope = wizard || form;
            const steps = [...scope.querySelectorAll('[data-step]')].filter((el) => {
                const gate = el.closest('[data-step-gate]');
                if (gate && window.getComputedStyle(gate).display === 'none') {
                    return false;
                }

                return window.getComputedStyle(el).display !== 'none';
            });
            const roots = steps.length ? steps : [form];
            const fields = [];
            roots.forEach((root) => {
                root.querySelectorAll('input, select, textarea').forEach((field) => {
                    if (field.disabled || field.type === 'hidden') {
                        return;
                    }
                    if (typeof field.checkVisibility === 'function' && ! field.checkVisibility()) {
                        return;
                    }
                    fields.push(field);
                });
            });

            return fields;
        },

        syncAndSubmit() {
            if (! this.form) {
                return;
            }
            this.formError = '';
            if (this.mode === 'activate_now') {
                if (! /^\d{4}$/.test(this.pin)) {
                    this.formError = 'Enter a 4-digit PIN to activate now.';
                    return;
                }
                if (this.pin !== this.pinConfirm) {
                    this.formError = 'PIN and confirmation must match.';
                    return;
                }
            }

            this.refreshSummary();
            const phone = this.syncPhone(this.form);
            if ((this.mode === 'activate_now' || this.mode === 'invite') && ! phone) {
                this.formError = 'Phone is entered on the Contact step of the form, not in this window. Use Back to form, add the number, then return. Or choose Save as inactive draft.';
                return;
            }

            const setHidden = (name, value) => {
                let el = this.form.querySelector(`[name="${CSS.escape(name)}"]`);
                if (! el) {
                    el = document.createElement('input');
                    el.type = 'hidden';
                    el.name = name;
                    this.form.appendChild(el);
                }
                el.value = value;
            };

            setHidden('activation_mode', this.mode);
            setHidden('notify_partner', this.notify ? '1' : '0');
            setHidden('activation_pin', this.mode === 'activate_now' ? this.pin : '');
            setHidden('status', this.mode === 'activate_now' ? 'active' : 'inactive');

            const invalid = this.activeFields(this.form).find((field) => typeof field.checkValidity === 'function' && ! field.checkValidity());
            if (invalid) {
                this.formError = invalid.validationMessage || 'Some required fields are still empty. Use Back to form, fix them, then return.';
                return;
            }

            const form = this.form;
            const confirmBtn = this.$el?.querySelector?.('[data-partner-confirm-create]');
            this.open = false;
            this.form = null;
            if (confirmBtn && typeof window.kfMarkBusy === 'function') {
                window.kfMarkBusy(confirmBtn);
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        },
    }));
}
