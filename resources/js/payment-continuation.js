/**
 * Shared post-payment continuation token.
 * Payment.show writes the destination before replace(); destination pages read it
 * before painting intermediate states (apply wizard fee/quote flash, etc.).
 */
const CONTINUE_KEY = 'kf-payment-continue';

export function preparePaymentContinuation(url) {
    if (! url) return;
    try {
        Object.keys(sessionStorage).forEach((key) => {
            if (! key.startsWith('kf-form-draft:')) return;
            if (key.includes('/borrower/apply') || key.includes('/apply')) {
                sessionStorage.removeItem(key);
            }
        });
        sessionStorage.setItem(CONTINUE_KEY, JSON.stringify({
            url: String(url),
            at: Date.now(),
        }));
        // Legacy key consumed by older apply-wizard builds during rollout.
        sessionStorage.setItem('kf-post-payment-continue', String(url));
    } catch (e) {
        // private mode — ignore
    }
}

export function consumePaymentContinuation(expectedPath = null) {
    try {
        const raw = sessionStorage.getItem(CONTINUE_KEY)
            || sessionStorage.getItem('kf-post-payment-continue');
        sessionStorage.removeItem(CONTINUE_KEY);
        sessionStorage.removeItem('kf-post-payment-continue');
        if (! raw) return null;
        let url = raw;
        try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed.url === 'string') {
                url = parsed.url;
            }
        } catch (e) {
            // legacy plain URL string
        }
        if (expectedPath && ! String(url).includes(expectedPath)) {
            return null;
        }
        return String(url);
    } catch (e) {
        return null;
    }
}

export function bindPaymentContinuation() {
    window.kfPreparePaymentContinuation = preparePaymentContinuation;
    window.kfConsumePaymentContinuation = consumePaymentContinuation;
}
