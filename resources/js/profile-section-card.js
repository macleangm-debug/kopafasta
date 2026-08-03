/**
 * Profile accordion card — kept out of Blade attributes to avoid quote breakage.
 */
export function registerProfileSectionCard(Alpine) {
    Alpine.data('profileSectionCard', (config = {}) => ({
        open: !!config.open,
        expanded: !!config.expanded,
        id: config.id || '',
        sectionHash: config.sectionHash || '',
        unsavedTitle: config.unsavedTitle || 'Leave without saving?',
        unsavedMessage: config.unsavedMessage || 'You have unsaved photos. Leave anyway?',
        unsavedConfirm: config.unsavedConfirm || 'Discard photos',
        _onAccordion: null,

        toggleExpand() {
            if (this.open) {
                return;
            }
            this.expanded = ! this.expanded;
            if (this.expanded) {
                window.dispatchEvent(new CustomEvent('profile-accordion', { detail: this.id }));
            }
        },

        openEdit() {
            this.open = true;
            this.expanded = true;
            window.dispatchEvent(new CustomEvent('profile-accordion', { detail: this.id }));
        },

        requestClose() {
            if (! this.open) {
                this.expanded = false;
                return;
            }

            const detail = {
                id: this.id,
                proceed: () => {
                    this.open = false;
                    this.expanded = false;
                },
                stay: () => {
                    this.open = true;
                    this.expanded = true;
                    window.dispatchEvent(new CustomEvent('profile-accordion', { detail: this.id }));
                },
            };

            const ev = new CustomEvent('profile-section-before-close', {
                bubbles: true,
                cancelable: true,
                detail,
            });
            this.$el.dispatchEvent(ev);
            if (ev.defaultPrevented) {
                return;
            }

            const form = this.$el.querySelector('form');
            const hasUnsavedFiles = !!(
                form
                && [...form.querySelectorAll('input[type="file"]')].some(
                    (input) => input.files && input.files.length > 0,
                )
            );

            if (hasUnsavedFiles) {
                if (typeof window.confirmForm === 'function') {
                    window.confirmForm(null, {
                        title: this.unsavedTitle,
                        message: this.unsavedMessage,
                        confirmLabel: this.unsavedConfirm,
                        confirmClass: 'bg-red-600 hover:bg-red-500 text-white',
                        onConfirm: () => detail.proceed(),
                        onCancel: () => detail.stay(),
                    });
                    return;
                }
                if (! window.confirm(this.unsavedMessage)) {
                    detail.stay();
                    return;
                }
            }

            detail.proceed();
        },

        init() {
            if (this.sectionHash && window.location.hash === `#${this.sectionHash}`) {
                this.open = true;
                this.expanded = true;
            }

            this._onAccordion = (e) => {
                if (e.detail === this.id) {
                    return;
                }
                if (this.open) {
                    this.requestClose();
                } else {
                    this.expanded = false;
                    this.open = false;
                }
            };
            window.addEventListener('profile-accordion', this._onAccordion);
        },

        destroy() {
            if (this._onAccordion) {
                window.removeEventListener('profile-accordion', this._onAccordion);
                this._onAccordion = null;
            }
        },
    }));
}
