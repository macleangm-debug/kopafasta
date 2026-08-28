/**
 * Field valuation camera: capture every required angle locally, then upload once.
 */
export function registerValuationCamera(Alpine) {
    Alpine.data('valuationCamera', (cfg = {}) => ({
        csrf: cfg.csrf || '',
        uploadUrl: cfg.uploadUrl || '',
        steps: cfg.steps || [],
        dbName: cfg.dbName || 'kf-valuation',
        step: cfg.step || 'asset',
        details: false,
        declineOpen: false,
        preview: null,
        afterPhotosUrl: cfg.afterPhotosUrl || '',
        assets: cfg.assets || [],
        valueLines: [],
        formMode: !!cfg.formMode,
        savingMessage: cfg.savingMessage || '',
        facingMode: cfg.facingMode || 'environment',
        open: false,
        review: false,
        uploading: false,
        uploadedCount: 0,
        failed: [],
        index: 0,
        retakeTarget: null,
        captures: {},
        cameraNotice: null,
        stream: null,
        flash: null,

        key(step) {
            return String(step.asset_id) + ':' + String(step.angle);
        },
        requiredSteps() {
            return this.steps.filter((s) => s.required);
        },
        optionalSteps() {
            return this.steps.filter((s) => ! s.required);
        },
        pendingRequired() {
            return this.requiredSteps().filter((s) => ! s.path && ! this.captures[this.key(s)]);
        },
        requiredDone() {
            return this.requiredSteps().filter((s) => s.path || this.captures[this.key(s)]).length;
        },
        requiredTotal() {
            return this.requiredSteps().length;
        },
        current() {
            if (this.retakeTarget) {
                return this.retakeTarget;
            }

            return this.pendingQueue()[this.index] || null;
        },
        captureOrdinal() {
            const step = this.current();
            if (! step) {
                return this.requiredDone();
            }
            if (! step.required) {
                return this.requiredDone();
            }
            const idx = this.requiredSteps().findIndex((s) => this.key(s) === this.key(step));

            return idx >= 0 ? idx + 1 : this.requiredDone() + 1;
        },
        nextLabel() {
            const queue = this.pendingQueue();
            const following = this.retakeTarget ? queue[0] : queue[1] || queue[0];

            return following && following !== this.current() ? following.label : null;
        },
        pendingQueue() {
            const pending = this.steps.filter((s) => s.required && ! s.path && ! this.captures[this.key(s)]);
            if (pending.length) {
                return pending;
            }

            return this.steps.filter((s) => ! s.required && ! s.path && ! this.captures[this.key(s)]);
        },
        localUrl(step) {
            return this.captures[this.key(step)]?.url || null;
        },
        thumbFor(step) {
            if (step.path) {
                return step.path_url;
            }

            return this.localUrl(step);
        },

        go(s) {
            this.step = s;
            if (s === 'review') {
                this.readValues();
            }
        },
        readValues() {
            this.valueLines = (this.assets || []).map((row) => {
                const market = this.$root?.querySelector?.(`[name="values[${row.id}][market_value]"]`)
                    || document.querySelector(`[name="values[${row.id}][market_value]"]`);
                const fsv = this.$root?.querySelector?.(`[name="values[${row.id}][forced_sale_value]"]`)
                    || document.querySelector(`[name="values[${row.id}][forced_sale_value]"]`);

                return {
                    label: row.label,
                    market: (market && market.value) ? market.value : '—',
                    fsv: (fsv && fsv.value) ? fsv.value : '—',
                };
            });
        },
        async init() {
            await this.restoreDrafts();
            if (this.pendingRequired().length === 0 && Object.keys(this.captures).length) {
                this.review = this.steps.some((s) => ! s.path && this.captures[this.key(s)]);
            }
        },
        async start(optionalOnly = false) {
            this.review = false;
            this.failed = [];
            this.retakeTarget = null;
            this.open = true;
            document.body.classList.add('kf-camera-open');
            const queue = optionalOnly
                ? this.steps.filter((s) => ! s.required && ! s.path && ! this.captures[this.key(s)])
                : this.pendingQueue();
            this.index = 0;
            if (! queue.length) {
                this.open = false;
                this.review = true;
                document.body.classList.remove('kf-camera-open');

                return;
            }
            await this.openCam();
        },
        closeCamera() {
            this.stopStream();
            this.open = false;
            document.body.classList.remove('kf-camera-open');
            if (this.requiredDone() > 0 && this.pendingRequired().length === 0) {
                this.review = true;
            }
        },
        async openCam() {
            this.cameraNotice = null;
            if (! window.isSecureContext) {
                this.cameraNotice = cfg.cameraInsecure || 'Camera needs HTTPS.';

                return;
            }
            try {
                await this.$nextTick();
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: this.facingMode }, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false,
                });
                const video = this.$refs.camVideo;
                if (! video) {
                    throw new Error('no video');
                }
                video.srcObject = this.stream;
                video.muted = true;
                await video.play();
            } catch (e) {
                this.cameraNotice = e?.name === 'NotAllowedError'
                    ? (cfg.cameraDenied || 'Camera permission denied.')
                    : (e?.message || 'Camera unavailable.');
            }
        },
        stopStream() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
        },
        capture() {
            const video = this.$refs.camVideo;
            const step = this.current();
            if (! video?.videoWidth || ! step) {
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            canvas.toBlob(async (blob) => {
                if (! blob) {
                    return;
                }
                const file = new File([blob], step.angle + '.jpg', { type: 'image/jpeg' });
                const url = URL.createObjectURL(blob);
                const key = this.key(step);
                if (this.captures[key]?.url) {
                    URL.revokeObjectURL(this.captures[key].url);
                }
                this.captures[key] = { file, url, angle: step.angle, asset_id: step.asset_id };
                await this.persistDraft(key, blob, file.name);
                this.retakeTarget = null;
                const queue = this.pendingQueue();
                this.flash = { label: step.label, next: queue[0]?.label || null };
                await new Promise((resolve) => setTimeout(resolve, 700));
                this.flash = null;
                if (queue.length) {
                    this.index = 0;
                } else {
                    this.stopStream();
                    this.open = false;
                    this.review = true;
                    document.body.classList.remove('kf-camera-open');
                }
            }, 'image/jpeg', 0.92);
        },
        retake(step) {
            const key = this.key(step);
            if (this.captures[key]?.url) {
                URL.revokeObjectURL(this.captures[key].url);
            }
            delete this.captures[key];
            this.deleteDraft(key);
            step.path = null;
            this.retakeTarget = step;
            this.review = false;
            this.open = true;
            document.body.classList.add('kf-camera-open');
            this.openCam();
        },
        async uploadAll() {
            const pending = this.steps.filter((s) => ! s.path && this.captures[this.key(s)]);
            if (! pending.length) {
                this.review = false;
                this.$dispatch('guided-photos-ready');
                this.$dispatch('valuation-photos-done');

                return;
            }
            if (this.formMode) {
                this.attachToForm();
                this.review = false;
                this.$dispatch('guided-photos-ready');

                return;
            }
            this.uploading = true;
            this.uploadedCount = 0;
            this.failed = [];
            const total = pending.length;
            if (typeof window.kfShowSaving === 'function') {
                window.kfShowSaving(this.savingMessage || '', { current: 0, total });
            }
            for (const step of pending) {
                const cap = this.captures[this.key(step)];
                try {
                    const body = new FormData();
                    body.append('_token', this.csrf);
                    body.append('customer_asset_id', String(step.asset_id));
                    body.append('angle', step.angle);
                    body.append('file', cap.file, cap.file.name || (step.angle + '.jpg'));
                    const res = await fetch(this.uploadUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body,
                    });
                    if (! res.ok) {
                        throw new Error('upload failed');
                    }
                    step.path = 'uploaded';
                    step.path_url = cap.url;
                    this.uploadedCount++;
                    if (typeof window.kfUpdateSaving === 'function') {
                        window.kfUpdateSaving({ current: this.uploadedCount, total });
                    }
                    await this.deleteDraft(this.key(step));
                } catch (e) {
                    this.failed.push(this.key(step));
                }
            }
            this.uploading = false;
            if (typeof window.kfHideSaving === 'function') {
                window.kfHideSaving();
            }
            if (this.failed.length === 0 && this.pendingRequired().length === 0) {
                this.review = false;
                this.$dispatch('valuation-photos-done');
                if (this.afterPhotosUrl) {
                    window.location = this.afterPhotosUrl;
                }
            }
        },
        attachToForm() {
            this.steps.forEach((step) => {
                const cap = this.captures[this.key(step)];
                if (! cap?.file || ! step.inputName) {
                    return;
                }
                const root = this.$root || this.$el;
                let input = root.querySelector(`input[data-guided-input="${step.inputName}"]`)
                    || root.querySelector(`input[name="${step.inputName}"]`);
                if (! input) {
                    input = document.createElement('input');
                    input.type = 'file';
                    input.name = step.inputName;
                    input.className = 'sr-only';
                    input.setAttribute('data-guided-input', step.inputName);
                    root.appendChild(input);
                }
                const dt = new DataTransfer();
                dt.items.add(cap.file);
                input.files = dt.files;
                step.path = 'local';
                step.path_url = cap.url;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        },
        retryFailed() {
            this.failed.forEach((key) => {
                const step = this.steps.find((s) => this.key(s) === key);
                if (step) {
                    step.path = null;
                }
            });
            this.uploadAll();
        },

        async db() {
            return new Promise((resolve, reject) => {
                const req = indexedDB.open(this.dbName, 1);
                req.onupgradeneeded = () => req.result.createObjectStore('shots');
                req.onsuccess = () => resolve(req.result);
                req.onerror = () => reject(req.error);
            });
        },
        async persistDraft(key, blob, name) {
            try {
                const db = await this.db();
                await new Promise((resolve, reject) => {
                    const tx = db.transaction('shots', 'readwrite');
                    tx.objectStore('shots').put({ blob, name, type: blob.type }, key);
                    tx.oncomplete = resolve;
                    tx.onerror = () => reject(tx.error);
                });
            } catch (e) { /* private mode */ }
        },
        async deleteDraft(key) {
            try {
                const db = await this.db();
                await new Promise((resolve) => {
                    const tx = db.transaction('shots', 'readwrite');
                    tx.objectStore('shots').delete(key);
                    tx.oncomplete = resolve;
                    tx.onerror = resolve;
                });
            } catch (e) { /* ignore */ }
        },
        async restoreDrafts() {
            try {
                const db = await this.db();
                const rows = await new Promise((resolve, reject) => {
                    const tx = db.transaction('shots', 'readonly');
                    const req = tx.objectStore('shots').getAll();
                    const keys = tx.objectStore('shots').getAllKeys();
                    tx.oncomplete = () => resolve({ values: req.result || [], keys: keys.result || [] });
                    tx.onerror = () => reject(tx.error);
                });
                rows.keys.forEach((key, i) => {
                    const row = rows.values[i];
                    if (! row?.blob) {
                        return;
                    }
                    const file = new File([row.blob], row.name || 'capture.jpg', { type: row.type || 'image/jpeg' });
                    const [assetId, angle] = String(key).split(':');
                    const step = this.steps.find((s) => this.key(s) === key);
                    if (step?.path) {
                        return;
                    }
                    this.captures[key] = {
                        file,
                        url: URL.createObjectURL(row.blob),
                        angle,
                        asset_id: Number(assetId),
                    };
                });
            } catch (e) { /* ignore */ }
        },
    }));
}
