export function registerPspPaymentFlow(Alpine) {
    Alpine.data('pspPaymentFlow', (cfg = {}) => ({
        state: cfg.initialState || 'details',
        paying: false,
        busy: false,
        message: cfg.message || '',
        paidTitle: cfg.paidTitle || '',
        amountLabel: cfg.amountLabel || '',
        phoneMasked: cfg.phoneMasked || '',
        paymentReference: cfg.paymentReference || '',
        successUrl: cfg.successUrl || '',
        payUrl: cfg.payUrl || '',
        statusUrl: cfg.statusUrl || '',
        retryUrl: cfg.retryUrl || '',
        gateUrl: cfg.gateUrl || '',
        overlay: cfg.overlay !== false,
        attempts: 0,
        maxAttempts: 36,
        startedAt: Date.now(),
        elapsedSec: 0,
        celebrated: false,
        timer: null,
        tickTimer: null,
        copy: cfg.copy || {},
        applyReward: cfg.applyReward || false,
        rewardDiscountLabel: cfg.rewardDiscountLabel || '',
        grossAmountLabel: cfg.grossAmountLabel || cfg.amountLabel || '',
        rewardNetLabel: cfg.rewardNetLabel || cfg.amountLabel || '',
        checkoutStep: 'adjust',
        cancelUrl: cfg.cancelUrl || '',
        adjustUrl: cfg.adjustUrl || '',
        promoCode: cfg.promoCode || '',
        promoMessage: '',
        promoValid: false,
        quoteLines: cfg.quoteLines || [],
        stackWithPromo: !!cfg.stackWithPromo,
        simulateUrl: cfg.simulateUrl || '',
        simulatorEnabled: !!cfg.simulatorEnabled,
        adjusting: false,

        formatLineAmount(line) {
            const amount = Number(line?.amount || 0);
            const abs = Math.abs(amount).toLocaleString();
            if (line?.kind === 'discount') return '− TZS ' + abs;
            return 'TZS ' + abs;
        },

        async postAdjust(payload) {
            if (!this.adjustUrl) {
                this.promoValid = false;
                this.promoMessage = this.copy.promoInvalid || this.copy.retry || '';
                return { res: { ok: false }, data: { ok: false, message: this.promoMessage } };
            }
            if (this.adjusting) {
                return { res: { ok: false }, data: { ok: false, message: this.promoMessage || this.copy.retry || '' } };
            }
            this.adjusting = true;
            try {
                const body = new FormData();
                const token = document.querySelector('meta[name=csrf-token]')?.content;
                if (token) body.append('_token', token);
                Object.entries(payload).forEach(([key, value]) => {
                    if (value === null || value === undefined) return;
                    body.append(key, value === true || value === false ? (value ? '1' : '0') : String(value));
                });
                const res = await fetch(this.adjustUrl, {
                    method: 'POST',
                    headers: this.csrfHeaders(),
                    credentials: 'same-origin',
                    body,
                });
                const data = await this.parseResponse(res);
                if (data.quote?.lines) this.quoteLines = data.quote.lines;
                if (data.amount_label) this.amountLabel = data.amount_label;
                if (data.promo_code !== undefined) this.promoCode = data.promo_code || this.promoCode;
                this.promoValid = !!data.promo_valid;
                this.promoMessage = data.message || '';
                return { res, data };
            } catch (e) {
                this.promoValid = false;
                this.promoMessage = this.copy.retry || this.copy.promoInvalid || '';
                return { res: { ok: false }, data: { ok: false, message: this.promoMessage } };
            } finally {
                this.adjusting = false;
            }
        },

        async applyPromo() {
            const code = String(this.promoCode || '').trim().toUpperCase();
            if (!code) {
                this.promoValid = false;
                this.promoMessage = this.copy.promoRequired || '';
                return;
            }
            this.promoCode = code;
            const result = await this.postAdjust({
                promo_code: code,
                apply_reward: this.applyReward ? '1' : '0',
            });
            if (!result) {
                this.promoValid = false;
                this.promoMessage = this.copy.promoInvalid || this.copy.retry || '';
                return;
            }
            if (!result.res.ok || result.data.ok === false) {
                this.promoValid = false;
                this.promoMessage = result.data.message || this.copy.promoInvalid || '';
            }
        },

        async toggleReward() {
            this.applyReward = !this.applyReward;
            this.amountLabel = this.applyReward ? this.rewardNetLabel : this.grossAmountLabel;
            await this.postAdjust({
                promo_code: this.stackWithPromo ? (this.promoCode || '') : '',
                apply_reward: this.applyReward ? '1' : '0',
                clear_promo: (!this.stackWithPromo && this.applyReward) ? '1' : '0',
            });
        },

        surfaceTitle() {
            if (this.state === 'paid') return this.copy.successTitle || this.paidTitle;
            if (this.state === 'failed') return this.copy.failedTitle;
            if (this.state === 'timeout') return this.copy.timeoutTitle;
            if (this.state === 'waiting') return this.copy.waitingTitle;
            return this.copy.payAmount || this.amountLabel;
        },

        elapsedLabel() {
            const m = Math.floor(this.elapsedSec / 60);
            const s = String(this.elapsedSec % 60).padStart(2, '0');
            return m + ':' + s;
        },

        csrfHeaders() {
            return {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
            };
        },

        applyPayload(data) {
            if (!data || typeof data !== 'object') return;
            if (data.amount_label) this.amountLabel = data.amount_label;
            if (data.phone_masked) this.phoneMasked = data.phone_masked;
            if (data.reference) this.paymentReference = data.reference;
            if (data.title) this.paidTitle = data.title;
            if (data.redirect_url) this.successUrl = data.redirect_url;
            if (data.message) this.message = data.message;

            const next = data.state === 'ready' ? 'details' : data.state;
            if (['details', 'waiting', 'paid', 'failed'].includes(next)) {
                this.state = next;
            }
            if (this.state === 'waiting') {
                if (!this.timer) this.startPolling();
            } else {
                this.stopTimers();
            }
            if (this.state === 'paid') {
                this.burstConfetti();
                if (this.successUrl) {
                    window.setTimeout(() => {
                        window.location.href = this.successUrl;
                    }, 1400);
                }
            }
        },

        async parseResponse(res) {
            try {
                return await res.json();
            } catch (e) {
                return { ok: false, message: this.copy.retry || 'Status check failed' };
            }
        },

        async payNow(form) {
            if (this.paying || this.busy) return;
            form.querySelectorAll('[data-phone-input]').forEach((root) => {
                if (typeof window.syncSitePhoneInput === 'function') {
                    window.syncSitePhoneInput(root);
                }
            });
            const method = form.querySelector('[name=payment_method]:checked')?.value
                || form.querySelector('[name=payment_method]')?.value
                || 'mobile_money';
            if (method === 'bank_transfer') {
                form.submit();
                return;
            }
            this.paying = true;
            this.message = '';
            try {
                const res = await fetch(this.payUrl, {
                    method: 'POST',
                    headers: this.csrfHeaders(),
                    credentials: 'same-origin',
                    body: new FormData(form),
                });
                const data = await this.parseResponse(res);
                if (!res.ok && data.state !== 'failed') {
                    this.message = data.message || this.copy.retry;
                    this.state = 'failed';
                    return;
                }
                this.applyPayload(data);
            } catch (e) {
                this.message = this.copy.retry;
                this.state = 'failed';
            } finally {
                this.paying = false;
            }
        },

        async poll() {
            if (this.state !== 'waiting' && this.state !== 'timeout') return;
            this.attempts += 1;
            try {
                const res = await fetch(this.statusUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await this.parseResponse(res);
                if (!res.ok || data.ok === false) {
                    this.message = data.message || this.copy.retry;
                    return;
                }
                if (data.state === 'paid' || data.state === 'failed' || data.state === 'ready') {
                    this.applyPayload(data);
                    return;
                }
                if (data.state === 'waiting') {
                    this.applyPayload(data);
                }
            } catch (e) {
                this.message = this.copy.retry;
            }
            if (this.state === 'waiting' && this.attempts >= this.maxAttempts) {
                this.stopTimers();
                this.state = 'timeout';
                this.message = this.copy.timeoutBody;
            }
        },

        async checkAgain() {
            this.attempts = 0;
            this.state = 'waiting';
            this.message = this.copy.waitingConfirmation;
            await this.poll();
            if (this.state === 'waiting') this.startPolling();
        },

        async tryAgain() {
            if (this.busy) return;
            this.busy = true;
            try {
                const res = await fetch(this.retryUrl, {
                    method: 'POST',
                    headers: this.csrfHeaders(),
                    credentials: 'same-origin',
                });
                const data = await this.parseResponse(res);
                this.applyPayload(data);
                if (!res.ok && this.state !== 'waiting' && this.state !== 'paid') {
                    this.state = 'failed';
                    this.message = data.message || this.copy.failedUsing;
                }
            } catch (e) {
                this.message = this.copy.retry;
                this.state = 'failed';
            } finally {
                this.busy = false;
            }
        },

        async changeNumber() {
            if (this.busy) return;
            this.busy = true;
            try {
                const res = await fetch(this.gateUrl, {
                    method: 'POST',
                    headers: this.csrfHeaders(),
                    credentials: 'same-origin',
                });
                const data = await this.parseResponse(res);
                this.stopTimers();
                this.paying = false;
                this.attempts = 0;
                this.state = 'details';
                this.message = '';
                if (data.phone_masked) this.phoneMasked = data.phone_masked;
            } catch (e) {
                this.message = this.copy.retry;
            } finally {
                this.busy = false;
            }
        },

        async simulateOutcome(outcome) {
            if (!this.simulatorEnabled || !this.simulateUrl || this.busy) return;
            this.busy = true;
            try {
                const body = new FormData();
                const token = document.querySelector('meta[name=csrf-token]')?.content;
                if (token) body.append('_token', token);
                body.append('outcome', outcome);
                const res = await fetch(this.simulateUrl, {
                    method: 'POST',
                    headers: this.csrfHeaders(),
                    credentials: 'same-origin',
                    body,
                });
                const data = await this.parseResponse(res);
                this.applyPayload(data);
                if (!res.ok && this.state !== 'waiting' && this.state !== 'paid') {
                    this.state = 'failed';
                    this.message = data.message || this.copy.retry;
                }
            } catch (e) {
                this.message = this.copy.retry;
                this.state = 'failed';
            } finally {
                this.busy = false;
            }
        },

        stopTimers() {
            if (this.timer) clearInterval(this.timer);
            if (this.tickTimer) clearInterval(this.tickTimer);
            this.timer = null;
            this.tickTimer = null;
        },

        startPolling() {
            this.stopTimers();
            this.startedAt = Date.now();
            this.elapsedSec = 0;
            this.tickTimer = setInterval(() => {
                this.elapsedSec = Math.floor((Date.now() - this.startedAt) / 1000);
            }, 1000);
            if (!this.timer) {
                this.timer = setInterval(() => this.poll(), 5000);
            }
        },

        burstConfetti() {
            if (this.celebrated) return;
            this.celebrated = true;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const confettiLayer = document.createElement('div');
            confettiLayer.setAttribute('aria-hidden', 'true');
            confettiLayer.style.cssText = 'position:fixed;inset:0;z-index:10120;pointer-events:none;overflow:visible;';
            document.body.appendChild(confettiLayer);
            const colors = ['#f5c842', '#10b981', '#004d40', '#0d9488', '#fbbf24', '#34d399', '#ffffff'];
            const originX = window.innerWidth / 2;
            const originY = Math.min(220, window.innerHeight * 0.28);
            for (let i = 0; i < 120; i++) {
                const piece = document.createElement('div');
                const angle = Math.random() * Math.PI * 2;
                const velocity = 8 + Math.random() * 18;
                const driftX = Math.cos(angle) * velocity * (14 + Math.random() * 18);
                const driftY = Math.sin(angle) * velocity * (6 + Math.random() * 10) - (40 + Math.random() * 80);
                const delay = Math.random() * 280;
                const duration = 2600 + Math.random() * 1400;
                const size = 5 + Math.random() * 7;
                const isRound = Math.random() > 0.55;
                piece.style.cssText = [
                    'position:absolute',
                    'top:' + originY + 'px',
                    'left:' + originX + 'px',
                    'width:' + size + 'px',
                    'height:' + (isRound ? size : (size * (1.2 + Math.random()))) + 'px',
                    'background:' + colors[i % colors.length],
                    'opacity:1',
                    'border-radius:' + (isRound ? '999px' : '2px'),
                    'pointer-events:none',
                    'will-change:transform,opacity',
                    'transform:translate(-50%,-50%) rotate(' + (Math.random() * 360) + 'deg)',
                    'transition:transform ' + duration + 'ms cubic-bezier(0.15,0.75,0.25,1), opacity ' + duration + 'ms ease-out',
                ].join(';');
                confettiLayer.appendChild(piece);
                (function (el, dx, dy, dly, dur) {
                    setTimeout(function () {
                        el.style.transform = 'translate(calc(-50% + ' + dx + 'px), calc(-50% + ' + (dy + window.innerHeight * 0.55) + 'px)) rotate(' + (Math.random() * 720) + 'deg)';
                        el.style.opacity = '0';
                    }, dly);
                    setTimeout(function () { el.remove(); }, dly + dur + 80);
                })(piece, driftX, driftY, delay, duration);
            }
            setTimeout(function () { confettiLayer.remove(); }, 5200);
        },

        init() {
            if (this.overlay) {
                document.documentElement.classList.add('overflow-hidden');
                document.body.classList.add('overflow-hidden');
            }
            if (this.state === 'waiting') {
                this.poll();
                this.startPolling();
            }
        },

        destroy() {
            this.stopTimers();
            if (this.overlay) {
                document.documentElement.classList.remove('overflow-hidden');
                document.body.classList.remove('overflow-hidden');
            }
        },
    }));
}
