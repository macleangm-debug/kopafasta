/**
 * Collateral add wizard — kept out of Blade attributes to avoid quote breakage.
 */
export function registerCollateralAddForm(Alpine) {
    Alpine.data('collateralAddForm', (config = {}) => ({
        saving: false,
        uploading: false,
        step: 1,
        photoIndex: 0,
        isVehicle: !!config.isVehicle,
        photoCount: Number(config.photoCount || 2),
        step1Ready: false,
        step2Ready: false,
        step3Ready: false,
        step4Ready: false,
        currentPhotoReady: false,
        allPhotosReady: false,
        _timer: null,
        get lastStep() {
            return this.isVehicle ? 5 : 3;
        },
        get photoStep() {
            return this.isVehicle ? 3 : 2;
        },
        get proofStep() {
            return this.isVehicle ? 4 : 3;
        },
        next() {
            if (this.step < this.lastStep) {
                this.step++;
            }
        },
        prev() {
            if (this.step > 1) {
                this.step--;
            }
        },
        formatThousands(el) {
            const digits = String(el.value || '').replace(/\D/g, '');
            el.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        refreshGates() {
            const form = this.$el;
            const complete = (scope) => window.KopaFastaForm?.isComplete(form, { onlyVisible: false, scope }) ?? false;
            this.step1Ready = complete(form.querySelector('[data-collateral-step="details"]'));
            this.step2Ready = ! this.isVehicle || complete(form.querySelector('[data-collateral-step="insurance"]'));
            this.step3Ready = complete(form.querySelector('[data-collateral-step="proof"]'));
            this.step4Ready = ! this.isVehicle || complete(form.querySelector('[data-collateral-step="cert"]'));
            const photoRoot = form.querySelector('[data-collateral-step="photos"]');
            const photoInputs = photoRoot ? [...photoRoot.querySelectorAll('input[type="file"][name^="photos"]')] : [];
            this.allPhotosReady = photoInputs.length > 0 && photoInputs.every((input) => input.files && input.files.length > 0);
            this.currentPhotoReady = this.allPhotosReady;
        },
        init() {
            this.refreshGates();
            this.$watch('step', () => this.$nextTick(() => this.refreshGates()));
            this.$watch('photoIndex', () => this.$nextTick(() => this.refreshGates()));
            this._timer = setInterval(() => this.refreshGates(), 400);
        },
        destroy() {
            if (this._timer) {
                clearInterval(this._timer);
            }
        },
    }));
}
