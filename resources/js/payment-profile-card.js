/**
 * Borrower Payment Accounts profile card — accordion state + add/edit panel.
 * Kept out of Blade attributes to avoid quote breakage and hydration mismatch.
 */
export function registerPaymentProfileCard(Alpine) {
    Alpine.data('paymentProfileCard', (config = {}) => ({
        expanded: !!config.expanded,
        complete: !!config.complete,
        showEditAction: !!config.showEditAction,
        adding: !!config.adding,
        editingId: Number(config.editingId) || 0,
        step: Number(config.step) || 1,
        type: config.type || '',
        mobileProvider: config.mobileProvider || '',
        mobileNumber: config.mobileNumber || '',
        bankName: config.bankName || '',
        accountNumber: config.accountNumber || '',
        bankBranch: config.bankBranch || '',
        editTitle: config.editTitle || 'Edit account',
        addTitle: config.addTitle || 'Add account',

        get showCompleteTick() {
            return this.complete && ! this.showEditAction && ! this.expanded;
        },

        get panelTitle() {
            return this.editingId ? this.editTitle : this.addTitle;
        },

        toggleExpand() {
            this.expanded = ! this.expanded;
            if (! this.expanded) {
                this.showEditAction = false;
            }
        },

        openAdd() {
            this.editingId = 0;
            this.type = '';
            this.mobileProvider = '';
            this.mobileNumber = '';
            this.bankName = '';
            this.accountNumber = '';
            this.bankBranch = '';
            this.step = 1;
            this.adding = true;
            this.expanded = true;
            this.showEditAction = true;
            this.$nextTick(() => this.$nextTick(() => this.clearPhoneInput()));
        },

        openEdit(account) {
            this.editingId = Number(account?.id) || 0;
            this.type = account?.type || '';
            this.mobileProvider = account?.mobile_provider || '';
            this.mobileNumber = account?.mobile_number || '';
            this.bankName = account?.bank_name || '';
            this.accountNumber = account?.account_number || '';
            this.bankBranch = account?.bank_branch || '';
            this.step = 2;
            this.adding = true;
            this.expanded = true;
            this.showEditAction = true;
            this.$nextTick(() => this.$nextTick(() => this.applyPhoneInput(this.mobileNumber)));
        },

        phoneRoot() {
            // action-panel teleports to body — do not search only inside this.$root
            return document.querySelector('[data-integration-live-test-panel] [data-phone-input]')
                || document.querySelector('[data-phone-input]');
        },

        clearPhoneInput() {
            const root = this.phoneRoot();
            if (! root || ! window.Alpine?.$data) {
                return;
            }
            const data = window.Alpine.$data(root);
            data.local = '';
            if (typeof data.syncHidden === 'function') {
                data.syncHidden();
            }
        },

        applyPhoneInput(full) {
            const root = this.phoneRoot();
            if (! root || ! window.Alpine?.$data) {
                return;
            }
            const data = window.Alpine.$data(root);
            const digits = String(full || '').replace(/\D/g, '');
            let local = digits;
            if (local.startsWith('255')) {
                local = local.slice(3);
            }
            local = local.replace(/^0+/, '');
            data.local = local;
            if (typeof data.syncHidden === 'function') {
                data.syncHidden();
            }
            this.mobileNumber = (typeof data.full === 'function' ? data.full() : '') || digits;
        },

        syncMobileNumber() {
            const input = this.phoneRoot()?.querySelector?.('input[name="mobile_number"][data-phone-hidden], input[name="mobile_number"]')
                || document.querySelector('input[name="mobile_number"][data-phone-hidden], input[name="mobile_number"]');
            if (input?.value) {
                this.mobileNumber = input.value;
            }
        },
    }));
}
