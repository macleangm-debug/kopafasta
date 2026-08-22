<x-admin.create-page
    title="New partner"
    heading="New partner"
    subheading="One section at a time — then confirm how this partner activates their portal"
    :action="route('admin.partners.store')"
    :cancelUrl="route('admin.partners.all')"
    submitLabel="Create partner"
    enctype="multipart/form-data"
    :confirmBeforeSubmit="true">
    @include('admin.partners._form', ['record' => null, 'creating' => true])
</x-admin.create-page>

{{-- Activation confirmation modal --}}
<div
    x-data="{
        open: false,
        form: null,
        mode: 'invite',
        notify: false,
        pin: '',
        pinConfirm: '',
        formError: '',
        summary: { name: '', category: '', entity: '', phone: '', email: '' },
        categoryLabels: @js($categories ?? []),
        fieldValue(form, name) {
            return form?.querySelector(`[name=\"${name}\"]`)?.value?.trim() || '';
        },
        syncPhone(form) {
            const wrap = form?.querySelector('[data-phone-input]');
            const hidden = form?.querySelector('input[name=\"phone\"]');
            if (! wrap || ! hidden) {
                return (hidden?.value || '').trim();
            }
            const local = wrap.querySelector('input[type=\"tel\"]')?.value || '';
            const prefix = wrap.querySelector('select')?.value || '';
            const digits = (prefix.replace(/\D/g, '') + local.replace(/\D/g, '').replace(/^0+/, ''));
            hidden.value = digits;
            return digits.trim();
        },
        refreshSummary() {
            if (! this.form) return;
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
            const steps = Array.from(scope.querySelectorAll('[data-step]')).filter((el) => {
                const gate = el.closest('[data-step-gate]');
                if (! gate) {
                    return true;
                }
                return window.getComputedStyle(gate).display !== 'none';
            });
            const fields = [];
            (steps.length ? steps : [form]).forEach((root) => {
                root.querySelectorAll('input, select, textarea').forEach((field) => {
                    if (field.disabled || field.type === 'hidden') {
                        return;
                    }
                    fields.push(field);
                });
            });
            return fields;
        },
        syncAndSubmit() {
            if (! this.form) return;
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
                let el = this.form.querySelector(`[name=\"${name}\"]`);
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
    }"
    x-on:admin-wizard-confirm-submit.window="openFor($event.detail.form)"
    x-cloak
>
    <div x-show="open" class="fixed inset-0 z-[10050] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-brand/70 backdrop-blur-sm" @click="cancel()"></div>
        <div class="relative w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-brand/15"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-3 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             @click.stop>
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white">
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">Confirm &amp; activate</p>
                <h3 class="text-xl font-bold mt-1">Create this partner?</h3>
                <p class="text-sm text-white/75 mt-1">This is portal access for the new partner — not a staff notification.</p>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div class="rounded-2xl bg-brand-muted/50 ring-1 ring-brand/10 p-4 text-sm">
                    <p class="font-bold text-gray-900" x-text="summary.name"></p>
                    <p class="text-gray-600 mt-0.5">
                        <span x-text="summary.category"></span><span x-show="summary.entity" x-cloak x-text="' · ' + summary.entity"></span>
                    </p>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <dt class="text-gray-500">Phone (from Contact)</dt>
                            <dd class="font-medium text-gray-900 mt-0.5" x-text="summary.phone"></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Email (from Contact)</dt>
                            <dd class="font-medium text-gray-900 mt-0.5 truncate" x-text="summary.email"></dd>
                        </div>
                    </dl>
                    <p class="text-[11px] text-gray-500 mt-3">These are a summary, not fields to type in. Use Back to form if a number is missing.</p>
                </div>

                <div x-show="formError" x-cloak class="rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800" x-text="formError"></div>

                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand">Portal activation</p>
                    <label class="flex items-start gap-3 rounded-xl ring-1 ring-brand/15 bg-white px-4 py-3 cursor-pointer"
                           :class="mode === 'invite' ? 'ring-brand bg-brand-muted/40' : ''">
                        <input type="radio" class="mt-1 text-brand focus:ring-brand" value="invite" x-model="mode">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Prepare activation invite</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Partner activates later with their partner code + phone (recommended).</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl ring-1 ring-brand/15 bg-white px-4 py-3 cursor-pointer"
                           :class="mode === 'activate_now' ? 'ring-brand bg-brand-muted/40' : ''">
                        <input type="radio" class="mt-1 text-brand focus:ring-brand" value="activate_now" x-model="mode">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Activate account now</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Create their portal login and set a PIN immediately.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl ring-1 ring-brand/15 bg-white px-4 py-3 cursor-pointer"
                           :class="mode === 'draft' ? 'ring-brand bg-brand-muted/40' : ''">
                        <input type="radio" class="mt-1 text-brand focus:ring-brand" value="draft" x-model="mode">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Save as inactive draft</span>
                            <span class="block text-xs text-gray-500 mt-0.5">No portal access yet — activate from the partner profile later.</span>
                        </span>
                    </label>
                </div>

                <div x-show="mode === 'invite'" x-cloak class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox" x-model="notify" class="rounded border-gray-300 text-brand focus:ring-brand">
                        Also send activation link by SMS / email (when messaging is enabled)
                    </label>
                </div>

                <div x-show="mode === 'activate_now'" x-cloak class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4 space-y-3">
                    <p class="text-xs text-amber-900">Phone from the Contact step is used for portal login.</p>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">PIN (4 digits)</label>
                            <input type="password" inputmode="numeric" maxlength="4" x-model="pin"
                                   class="w-full rounded-xl border-gray-300 text-sm tracking-widest font-mono"
                                   placeholder="••••" autocomplete="new-password">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Confirm PIN</label>
                            <input type="password" inputmode="numeric" maxlength="4" x-model="pinConfirm"
                                   class="w-full rounded-xl border-gray-300 text-sm tracking-widest font-mono"
                                   placeholder="••••" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end pt-1">
                    <button type="button" @click="cancel()"
                            class="inline-flex justify-center px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-white ring-1 ring-gray-200 hover:bg-gray-50">
                        Back to form
                    </button>
                    <button type="button"
                            data-partner-confirm-create
                            @click="syncAndSubmit()"
                            :disabled="(mode === 'invite' || mode === 'activate_now') && (summary.phone === '—' || ! summary.phone)"
                            class="inline-flex justify-center items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-brand bg-brand-gold hover:brightness-95 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        Confirm &amp; create
                    </button>
                </div>
                <p x-show="(mode === 'invite' || mode === 'activate_now') && (summary.phone === '—' || ! summary.phone)"
                   x-cloak
                   class="text-xs text-amber-800 text-right">
                    Use Back to form and add a phone on Contact, or choose Save as inactive draft.
                </p>
            </div>
        </div>
    </div>
</div>
