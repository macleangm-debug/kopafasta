/**
 * Profile accordion card — kept out of Blade attributes to avoid quote breakage.
 */
export function registerProfileSectionCard(Alpine) {
    Alpine.data('profileSectionCard', (config = {}) => ({
        open: !!config.open,
        expanded: !!config.expanded,
        complete: !!config.complete,
        showEditAction: !!config.showEditAction,
        emptyOpensView: !!config.emptyOpensView,
        editAllowed: config.editAllowed !== false,
        id: config.id || '',
        sectionHash: config.sectionHash || '',
        unsavedTitle: config.unsavedTitle || 'Leave without saving?',
        unsavedMessage: config.unsavedMessage || 'You have unsaved photos. Leave anyway?',
        unsavedConfirm: config.unsavedConfirm || 'Discard photos',
        _onAccordion: null,

        get showCompleteTick() {
            if (! this.editAllowed && this.complete) {
                return ! this.open && ! this.expanded;
            }
            // Collapsed + complete only — Complete is lasting status; never "Saved".
            return this.complete && ! this.open && ! this.expanded && ! this.showEditAction;
        },

        /** Edit / Hariri only while viewing or editing — never on a collapsed card. */
        get showHeaderEdit() {
            if (! this.editAllowed) {
                return false;
            }
            if (this.showCompleteTick) {
                return false;
            }
            return this.expanded || this.open;
        },

        toggleExpand() {
            // Header click again collapses — including when Hariri/Edit is open.
            if (this.open) {
                this.requestClose();
                return;
            }
            this.expanded = ! this.expanded;
            if (this.expanded) {
                this.showEditAction = false;
                window.dispatchEvent(new CustomEvent('profile-accordion', { detail: this.id }));
            } else {
                this.showEditAction = false;
            }
        },

        revealEdit() {
            if (! this.editAllowed) {
                this.openView();
                return;
            }
            this.showEditAction = true;
            this.expanded = true;
            window.dispatchEvent(new CustomEvent('profile-accordion', { detail: this.id }));
            window.dispatchEvent(new CustomEvent('profile-section-edit', { detail: this.sectionHash }));
        },

        /** Expand the view slot without opening the edit form (e.g. NIDA Front→Back holder). */
        openView() {
            this.open = false;
            this.expanded = true;
            this.showEditAction = false;
            window.dispatchEvent(new CustomEvent('profile-accordion', { detail: this.id }));
            if (this.sectionHash === 'profile-id-images') {
                this.$nextTick(() => {
                    window.dispatchEvent(new CustomEvent('nida-open-source'));
                });
            }
        },

        openEdit() {
            if (! this.editAllowed) {
                this.openView();
                return;
            }
            this.open = true;
            this.expanded = true;
            this.showEditAction = true;
            window.dispatchEvent(new CustomEvent('profile-accordion', { detail: this.id }));
            window.dispatchEvent(new CustomEvent('profile-section-edit', { detail: this.sectionHash }));
        },

        requestClose() {
            if (! this.open) {
                this.expanded = false;
                this.showEditAction = false;
                return;
            }

            const detail = {
                id: this.id,
                proceed: () => {
                    // Leave the View panel open so mirrored autosave values are visible
                    // without a reload (collapsing hid View and looked like a save failure).
                    this.open = false;
                    this.expanded = true;
                    this.showEditAction = false;
                },
                stay: () => {
                    this.open = true;
                    this.expanded = true;
                    this.showEditAction = true;
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
            // Deep-link hash expands to VIEW (not edit) so users can preview first.
            if (this.sectionHash && window.location.hash === `#${this.sectionHash}`) {
                this.expanded = true;
                if (! this.open) {
                    this.open = false;
                }
                // Keep the complete tick — do not force Edit to appear (that caused overlap).
                if (this.complete) {
                    this.showEditAction = false;
                }
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
                    this.showEditAction = false;
                }
            };
            window.addEventListener('profile-accordion', this._onAccordion);
            this._onCloseEdit = () => {
                this.open = false;
                this.showEditAction = false;
                this.expanded = true;
            };
            this.$el.addEventListener('profile-section-close-edit', this._onCloseEdit);
            window.addEventListener('profile-section-close-edit', this._onCloseEdit);
        },

        destroy() {
            if (this._onAccordion) {
                window.removeEventListener('profile-accordion', this._onAccordion);
                this._onAccordion = null;
            }
            if (this._onCloseEdit) {
                this.$el.removeEventListener('profile-section-close-edit', this._onCloseEdit);
                window.removeEventListener('profile-section-close-edit', this._onCloseEdit);
                this._onCloseEdit = null;
            }
        },
    }));
}
