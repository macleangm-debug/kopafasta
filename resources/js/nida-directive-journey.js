/**
 * National ID Front→Back directive journey — same sequential state machine as face,
 * parameterized to 2 steps with landscape ID capture (Camera / Upload).
 */
export function registerNidaDirectiveJourney(Alpine) {
    Alpine.data('nidaDirectiveJourney', (config = {}) => ({
        steps: (config.steps || []).map((s) => ({ ...s })),
        updateUrl: config.updateUrl || '',
        csrf: config.csrf || '',
        nationalId: config.nationalId || '',
        returnUrl: config.returnUrl || '',
        stepIndex: 0,
        phase: 'journey', // journey | complete
        saving: false,
        notice: null,
        replaceSide: null,

        get current() {
            return this.steps[this.stepIndex] || null;
        },

        get allDone() {
            return this.steps.every((s) => s.done && s.previewUrl);
        },

        init() {
            const firstMissing = this.steps.findIndex((s) => !s.done);
            if (firstMissing < 0) {
                this.phase = 'complete';
                this.stepIndex = 0;
            } else {
                this.phase = 'journey';
                this.stepIndex = firstMissing;
            }

            this._onFile = (event) => {
                const hostId = event.detail?.hostId;
                const file = event.detail?.file;
                if (!file || !hostId) return;
                const step = this.steps.find((s) => s.hostId === hostId);
                if (!step) return;
                // Prevent nested form submit — we persist via fetch.
                event.stopPropagation?.();
                this.persistSide(step, file);
            };
            window.addEventListener('kf-document-file', this._onFile);

            this._onReplace = (event) => {
                const side = event.detail?.side;
                if (!side) return;
                this.beginReplace(side);
            };
            window.addEventListener('nida-holder-replace', this._onReplace);
        },

        destroy() {
            if (this._onFile) window.removeEventListener('kf-document-file', this._onFile);
            if (this._onReplace) window.removeEventListener('nida-holder-replace', this._onReplace);
        },

        beginReplace(side) {
            const idx = this.steps.findIndex((s) => s.key === side);
            if (idx < 0) return;
            this.replaceSide = side;
            this.stepIndex = idx;
            this.phase = 'journey';
            this.notice = null;
            this.$nextTick(() => {
                window.dispatchEvent(new CustomEvent('clear-capture', {
                    detail: { hostId: this.steps[idx].hostId },
                }));
            });
        },

        async persistSide(step, file) {
            if (this.saving || !this.updateUrl) return;
            this.saving = true;
            this.notice = null;
            if (typeof window.kfShowInlineSaving === 'function') {
                window.kfShowInlineSaving(config.savingLabel || 'Saving…');
            }

            const fd = new FormData();
            fd.append('_token', this.csrf);
            fd.append('_method', 'PUT');
            fd.append('focus', 'id_images');
            fd.append(step.field, file, file.name || `${step.key}.jpg`);
            if (this.nationalId) {
                fd.append('national_id', this.nationalId);
            }
            if (this.returnUrl) {
                fd.append('return', this.returnUrl);
            }

            try {
                const res = await fetch(this.updateUrl, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.ok === false) {
                    throw new Error(data.message || config.failLabel || 'Could not save · Retry');
                }

                step.done = true;
                step.previewUrl = data.previewUrl || step.previewUrl || URL.createObjectURL(file);
                this.steps = this.steps.map((s) => ({ ...s }));

                if (typeof window.kfFlashInlineSaved === 'function') {
                    window.kfFlashInlineSaved(config.savedLabel || 'Saved');
                } else if (typeof window.kfHideSaving === 'function') {
                    window.kfHideSaving();
                }

                if (data.complete || this.allDone) {
                    this.phase = 'complete';
                    this.replaceSide = null;
                    return;
                }

                // Advance to next missing side (Front → Back). Never treat Front alone as complete.
                const next = this.steps.findIndex((s) => !s.done);
                this.stepIndex = next >= 0 ? next : this.stepIndex;
                this.phase = 'journey';
                this.replaceSide = null;
            } catch (e) {
                this.notice = e.message || config.failLabel || 'Could not save · Retry';
                if (typeof window.kfHideSaving === 'function') {
                    window.kfHideSaving();
                }
            } finally {
                this.saving = false;
            }
        },
    }));
}
