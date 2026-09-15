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
                return ! this.open;
            }
            // Complete tick while collapsed or in View — never while Edit form is open.
            return this.complete && ! this.open && ! this.showEditAction;
        },

        /** Edit / Hariri only while viewing or editing — never on a collapsed complete card. */
        get showHeaderEdit() {
            if (! this.editAllowed) {
                return false;
            }
            if (this.complete && ! this.expanded && ! this.open) {
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
            const params = new URLSearchParams(window.location.search);
            const wantEdit = params.get('edit') === '1' || !!params.get('field');
            const field = params.get('field');

            // Deep-link: hash + edit/field opens Add/Edit and focuses the field.
            if (this.sectionHash && window.location.hash === `#${this.sectionHash}`) {
                if (wantEdit && this.editAllowed) {
                    this.open = true;
                    this.expanded = true;
                    this.showEditAction = true;
                } else {
                    this.expanded = true;
                    if (! this.open) {
                        this.open = false;
                    }
                    if (this.complete) {
                        this.showEditAction = false;
                    }
                }
            }

            if (field && (this.open || wantEdit)) {
                this.$nextTick(() => {
                    const selectors = [
                        `[name="${CSS.escape(field)}"]`,
                        `[name="activity_details[${CSS.escape(field)}]"]`,
                        `[name="${CSS.escape(field)}[]"]`,
                        `#${CSS.escape(field)}`,
                    ];
                    let el = null;
                    for (const sel of selectors) {
                        try {
                            el = this.$el.querySelector(sel);
                        } catch (e) {
                            el = null;
                        }
                        if (el) {
                            break;
                        }
                    }
                    if (el && typeof el.focus === 'function') {
                        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                        el.focus({ preventScroll: true });
                    }
                });
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
