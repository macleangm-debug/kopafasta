/**
 * Shared Integration Live Test / PayIn operational rehearsal panel.
 * Kept out of inline Blade x-data so HTML attribute quoting cannot break Alpine.
 */
export function registerIntegrationLiveTest(Alpine) {
    Alpine.data('integrationLiveTest', (cfg = {}) => ({
        liveTestOpen: Boolean(cfg.autoOpen),
        step: 'form',
        phoneDisplay: '',
        amountDisplay: '1,000',
        messagePreview: '',
        emailTo: '',
        emailSubject: cfg.emailSubject || 'Kopafasta email live test',
        nidaDisplay: '',
        riskAck: false,
        requireRiskAck: Boolean(cfg.requireRiskAck),
        submitting: false,

        markBusy(el, label) {
            if (el && typeof window.kfMarkBusy === 'function') {
                window.kfMarkBusy(el, label);
            }
        },

        clearBusy(el) {
            if (el && typeof window.kfClearBusy === 'function') {
                window.kfClearBusy(el);
            }
        },

        openLiveTest(event) {
            const btn = event?.currentTarget instanceof HTMLElement
                ? event.currentTarget
                : this.$el.querySelector('[data-live-test-trigger]');
            this.markBusy(btn, 'Opening…');
            this.step = 'form';
            this.riskAck = false;
            this.liveTestOpen = true;
            queueMicrotask(() => this.clearBusy(btn));
        },

        closeLiveTest() {
            this.liveTestOpen = false;
            this.step = 'form';
            this.submitting = false;
        },

        formatPhone(raw) {
            const digits = String(raw || '').replace(/\D/g, '');
            if (! digits) {
                return '';
            }
            if (digits.startsWith('255') && digits.length >= 12) {
                return `+255 ${digits.slice(3)}`;
            }

            return `+255 ${digits.replace(/^0+/, '')}`;
        },

        formatAmount(raw) {
            const n = Number(String(raw || '').replace(/,/g, ''));
            if (! Number.isFinite(n) || n <= 0) {
                return '1,000';
            }

            return n.toLocaleString('en-US', { maximumFractionDigits: 0 });
        },

        goReview() {
            const form = this.$refs.liveTestForm;
            if (! form) {
                return;
            }
            if (typeof form.reportValidity === 'function' && ! form.reportValidity()) {
                return;
            }
            if (this.requireRiskAck && ! this.riskAck) {
                return;
            }

            const valueOf = (name) => form.querySelector(`[name="${name}"]`)?.value || '';

            this.phoneDisplay = this.formatPhone(valueOf('phone'));
            this.amountDisplay = this.formatAmount(valueOf('amount') || '1000');
            this.messagePreview = valueOf('message');
            this.emailTo = valueOf('email');
            this.emailSubject = valueOf('subject') || (cfg.emailSubject || 'Kopafasta email live test');
            this.nidaDisplay = valueOf('nida');
            this.step = 'review';
        },

        backToForm() {
            if (this.submitting) {
                return;
            }
            this.step = 'form';
        },

        submitLiveTest(event) {
            if (this.submitting) {
                return;
            }

            const form = this.$refs.liveTestForm;
            if (! form) {
                return;
            }
            if (this.requireRiskAck && this.$refs.riskAckInput) {
                this.$refs.riskAckInput.checked = true;
            }

            const btn = event?.currentTarget instanceof HTMLElement
                ? event.currentTarget
                : form.querySelector('[data-live-test-continue]');
            const label = btn?.dataset?.loadingLabel || 'Opening payment…';
            this.submitting = true;
            this.markBusy(btn, label);
            form.requestSubmit();
        },

        init() {
            this.$watch('liveTestOpen', (open) => {
                if (! open) {
                    this.step = 'form';
                    this.submitting = false;
                }
            });
        },
    }));
}
