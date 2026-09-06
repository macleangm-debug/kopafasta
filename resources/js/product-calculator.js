export function registerProductCalculator(Alpine) {
    Alpine.data('productCalculator', (config = {}) => ({
        config,
        amount: Number(config.min || 0),
        tenure: Number(config.tmin || 1),
        installment: 0,
        total: 0,
        loading: false,
        error: null,
        _timer: null,

        init() {
            this.refresh();
            this.$watch('amount', () => this.schedule());
            this.$watch('tenure', () => this.schedule());
        },

        schedule() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.refresh(), 180);
        },

        async refresh() {
            if (!this.config.quoteUrl) {
                return;
            }
            this.loading = true;
            this.error = null;
            try {
                const url = new URL(this.config.quoteUrl, window.location.origin);
                url.searchParams.set('amount', String(this.amount));
                url.searchParams.set('tenure', String(this.tenure));
                const res = await fetch(url.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) {
                    throw new Error('quote_failed');
                }
                const data = await res.json();
                const cadence = this.config.cadence === 'weekly' ? 'weekly' : 'monthly';
                this.installment = Number(
                    cadence === 'weekly' ? data.weekly_installment : data.monthly_installment
                ) || 0;
                this.total = Number(data.total_repayment) || 0;
            } catch (e) {
                this.error = true;
            } finally {
                this.loading = false;
            }
        },

        formatMoney(value) {
            const n = Number(value) || 0;
            if (typeof window.formatMoney === 'function') {
                return window.formatMoney(n);
            }
            return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(n);
        },
    }));
}
