
function showWizardFeedback(message, detail = {}) {
    const payload = typeof message === 'string'
        ? { message, tone: detail.tone || 'error', title: detail.title, lines: detail.lines }
        : message;
    if (typeof window.showBorrowerFeedback === 'function') {
        window.showBorrowerFeedback(payload);
        return;
    }
    const text = payload.message || (Array.isArray(payload.lines) ? payload.lines.join('\n') : '');
    if (text) window.alert(text);
}

export function applyWizard(config) {
            return {
                products: config.products,
                applicationFee: config.applicationFee,
                applicationFeePayUrl: config.applicationFeePayUrl || '',
                applicationFeeQuoteUrl: config.applicationFeeQuoteUrl || '',
                applicationFeeState: config.savedDraft?.application_fee || null,
                applicationFeePaid: false,
                valuationFeePayUrl: config.valuationFeePayUrl || '',
                valuationFeeQuoteUrl: config.valuationFeeQuoteUrl || '',
                assetDocumentUploadUrl: config.assetDocumentUploadUrl || '',
                assetTypeOptions: config.assetTypeOptions || {},
                assetDocumentLabels: config.assetDocumentLabels || {},
                customerAssets: config.customerAssets || [],
                valuationFeeAmount: config.valuationFeeAmount || 0,
                valuationFeeState: config.savedDraft?.valuation_fee || null,
                valuationFeePaid: false,
                valuationFeeChannel: 'mobile_money',
                valuationFeePhone: config.paymentPhone || '',
                valuationFeePaying: false,
                valuationFeePaymentReference: config.valuationFeePaymentReference ?? null,
                assetDocuments: config.savedDraft?.asset_documents || {},
                assetDocumentUploading: false,
                feeChannel: 'mobile_money',
                feePhone: config.paymentPhone || '',
                feeUseWallet: false,
                feePromoCode: '',
                feeQuoteData: config.feeQuoteData ?? null,
                feePaying: false,
                feePaymentReference: config.feePaymentReference ?? null,
                feeNotice: null,
                feeLoyaltyOption: config.feeLoyaltyOption || null,
                feeRedeemLoyalty: !!(config.feeLoyaltyOption?.can_redeem),
                _gateTick: 0,
                returnTo: config.returnTo || null,
                guarantorInviteError: '',
                stepNotice: null,
                purposeLabels: config.purposeLabels,
                kinRelationshipLabels: config.kinRelationshipLabels || {},
                productQuestions: config.productQuestions,
                profileSections: config.profileSections,
                incomeVerification: config.incomeVerification,
                readinessUrl: config.readinessUrl,
                loanProductsUrl: config.loanProductsUrl || '',
                guarantorLookupUrl: config.guarantorLookupUrl || '',
                groupMemberLookupUrl: config.groupMemberLookupUrl || '',
                groupMemberInviteUrl: config.groupMemberInviteUrl || '',
                groupMemberExpireUrl: config.groupMemberExpireUrl || '',
                groupMemberStatusesUrl: config.groupMemberStatusesUrl || '',
                previousGroupMembersUrl: config.previousGroupMembersUrl || '',
                selectPreviousGroupMemberUrl: config.selectPreviousGroupMemberUrl || '',
                groupLimits: config.groupLimits || { min: 3, max: 10, minAmountPerMember: 200000 },
                leaderCustomerId: config.leaderCustomerId || null,
                leaderName: config.leaderName || '',
                leaderPhone: config.leaderPhone || '',
                leaderAvatarUrl: config.leaderAvatarUrl || null,
                group: config.savedDraft?.group || { name: '', purpose: '', target_member_count: null, amount_per_member: 0, members: [] },
                groupMemberMode: 'internal',
                addMemberOpen: false,
                addGuarantorOpen: false,
                feeGateOpen: false,
                groupMemberLookup: { ok: false, label: '', error: '', data: null },
                groupExternal: { first_name: '', last_name: '', phone: '' },
                groupExternalInvite: null,
                groupInviteLoading: false,
                groupProgressLabels: config.groupProgressLabels || {},
                groupProgressSummary: null,
                groupApplicationStatus: null,
                groupScoring: null,
                groupFeeBreakdownData: null,
                groupLookupMemberNo: '',
                groupLookupPhone: '',
                groupLookupLoading: false,
                groupLookupError: '',
                previousGroupMembers: [],
                guarantorInviteUrl: config.guarantorInviteUrl || '',
                previousGuarantorsUrl: config.previousGuarantorsUrl || '',
                selectPreviousGuarantorUrl: config.selectPreviousGuarantorUrl || '',
                previousGuarantors: [],
                guarantorStatusUrl: config.guarantorStatusUrl || '',
                guarantorExpireUrl: config.guarantorExpireUrl || '',
                repaymentPreviewUrl: config.repaymentPreviewUrl || '',
                borrowerSnapshot: config.borrowerSnapshot || {},
                incomeRangeLabels: config.incomeRangeLabels || {},
                activityTypeLabels: config.activityTypeLabels || {},
                tanzaniaLocations: config.tanzaniaLocations || {},
                draftSaveUrl: config.draftSaveUrl || '',
                reservationId: config.reservationId || null,
                draftSavedAt: null,
                draftSaveTimer: null,
                draftReference: config.savedDraft?.draft_reference || '',
                profileSignature: config.profileSignature || null,
                borrowerSignature: config.savedDraft?.borrower_signature || config.profileSignature || null,
                guarantorLookup: { ok: false, label: '', error: '', memberKey: '', phone: '', name: '' },
                guarantorValidating: false,
                guarantorChanging: false,
                externalGuarantor: config.savedDraft?.external_guarantor || null,
                internalGuarantor: config.savedDraft?.internal_guarantor || null,
                guarantorInvitePreparing: false,
                advancing: false,
                submitting: false,
                resumeLoading: false,
                furthestStep: 0,
                showProfileGateModal: false,
                showProfileReadyModal: false,
                showMembershipGateModal: false,
                showAlreadyMemberModal: false,
                alreadyMemberModal: { name: '', phone: '' },
                openProfileGateOnLoad: !!(config.openProfileGateOnLoad),
                openProfileReadyOnLoad: !!(config.openProfileReadyOnLoad),
                isResume: !! config.isResume,
                guarantorErrors: {},
                externalInviteTimer: null,
                initialPlan: config.initialPlan || [],
                assetApplication: config.assetApplication || null,
                reservationMode: !! config.reservationMode,
                marketplaceOnlyCodes: config.marketplaceOnlyCodes || [],
                marketplaceUrl: config.marketplaceUrl || '',
                profileUrl: config.profileUrl || '',
                membershipRenewUrl: config.membershipRenewUrl || '',
                hasActiveMembership: !! config.hasActiveMembership,
                canApply: !! config.canApply,
                firstActionUrl: config.firstActionUrl || null,
                verifiedLegalName: config.verifiedLegalName || '',
                identityVerified: !! config.identityVerified,
                engagementBoosts: config.engagementBoosts || null,
                qualificationLimit: Number(config.qualificationLimit || 0),
                processingSla: config.processingSla || null,
                loyaltyRateDiscount: Number(config.loyaltyRateDiscount || 0),
                activeRewards: config.activeRewards || [],
                pointsBalance: Number(config.pointsBalance || 0),
                declarationAccepted: !!(config.savedDraft?.declaration_accepted || config.savedDraft?.borrower_signature || config.profileSignature),
                declarationSaveTimer: null,
                resigningOnSubmit: false,
                i18n: config.i18n,
                phase: 'details',
                readiness: null,
                readinessLoading: false,
                steps: [],
                step: 0,
                stepKey: '',
                current: null,
                form: {
                    loan_product_id: null,
                    requested_amount: 0,
                    requested_tenure_months: 0,
                    purpose: '',
                    purpose_other: '',
                    guarantor_mode: '',
                    internal_member_no: '',
                    internal_guarantor_phone: '',
                    internal_guarantor_name: '',
                    income_type: 'bank',
                    external_first_name: '',
                    external_middle_name: '',
                    external_last_name: '',
                    external_relationship: '',
                    external_phone: '',
                    external_email: '',
                    external_region: '',
                    external_district: '',
                    asset_type: '',
                    asset_description: '',
                    customer_asset_id: '',
                    customer_asset_ids: [],
                },
                quote: { monthly: 0, weekly: 0, primary: 0, frequency: 'monthly', interest: 0, total: 0, fees: 0 },
                purposeEditing: false,
                review: { personal: '', residence: '', employment: '', nok: '', activity: '', guarantor: '', guarantorType: '', guarantorName: '', guarantorStatus: '' },
                reviewSummary: { monthly_rate_pct: 0, application_fee: 0, monthly_installment: 0, installment_amount: null, repayment_cadence: 'monthly' },
                repaymentSchedule: [],
                scheduleDatesAvailable: false,
                scheduleLoading: false,
                reviewPage: 1,
                reviewPageCount: 2,
                supplementMode: !!config.supplementMode,
                supplementApplicationId: config.supplementApplicationId || null,
                stepIcons: {
                    quote: '💰',
                    group_setup: '👥',
                    group_members: '📋',
                    asset_details: '🚗',
                    valuation_fee: '📋',
                    asset_tenure: '📅',
                    application_fee: '💳',
                    guarantor: '🤝',
                    product_questions: '📄',
                    review: '✅',
                    signature: '✍️',
                    submit: '📤',
                },

                syncStepKey() {
                    this.stepKey = this.steps[this.step]?.key ?? '';
                    this.syncUrlStep();
                },

                syncUrlStep() {
                    try {
                        const url = new URL(window.location.href);
                        if (this.phase === 'application' && this.stepKey) {
                            url.searchParams.set('resume', '1');
                            url.searchParams.set('step_key', this.stepKey);
                            window.history.replaceState({}, '', url.pathname + url.search + url.hash);
                        }
                    } catch (e) { /* ignore */ }
                },

                bumpFurthest(index = this.step) {
                    this.furthestStep = Math.max(this.furthestStep || 0, index || 0);
                },

                resolveStepIndex(stepKey, fallbackIndex = 0) {
                    if (stepKey) {
                        const byKey = this.steps.findIndex(s => s.key === stepKey);
                        if (byKey >= 0) return byKey;
                    }
                    return Math.min(Math.max(0, fallbackIndex), Math.max(0, this.steps.length - 1));
                },

                districtsForRegion() {
                    const r = this.form.external_region;
                    return r && this.tanzaniaLocations[r] ? this.tanzaniaLocations[r] : [];
                },

                init() {
                    this.syncFeePaidState();
                    this.syncValuationFeePaidState();
                    window.applyWizardSaveDraft = () => this.persistDraft(true);
                    setInterval(() => { this._gateTick++; }, 400);
                    this.$watch('phase', (value, oldValue) => {
                        this.scheduleDraftSave();
                        if (value === 'application' && oldValue !== 'application') {
                            this.persistDraft(true);
                        }
                    });
                    this.$watch('step', () => {
                        this.bumpFurthest(this.step);
                        this.syncStepKey();
                        // Persist immediately so language switches keep the visible step.
                        this.persistDraft(true);
                    });
                    this.$watch('stepKey', (key) => {
                        if (key === 'application_fee') {
                            this.enterApplicationFeeStep();
                        }
                        if (key === 'guarantor' || key === 'submit' || key === 'review') {
                            this.refreshGuarantorStatus();
                        }
                        if (key === 'guarantor') {
                            this.loadPreviousGuarantors();
                        }
                        if (key === 'group_members') {
                            this.loadPreviousGroupMembers();
                            this.refreshGroupMemberStatuses();
                        }
                        if (key === 'signature') {
                            this.$nextTick(() => this.restoreSignaturePad());
                        }
                        if (key === 'submit') {
                            this.$nextTick(() => this.syncSubmitPayload(this.formRoot()));
                        }
                        if (key === 'review') {
                            this.$nextTick(() => this.refreshReview(this.formRoot()));
                        }
                        if (key === 'quote' || key === 'asset_details' || key === 'group_setup') {
                            // Keep purpose locked when set — but force edit open if "other" still needs detail.
                            this.purposeEditing = this.purposeNeedsDetail();
                        }
                        if (key === 'quote' && this.isGroupProduct(this.current)) {
                            if (! this.group.amount_per_member) {
                                this.group.amount_per_member = this.groupAmountPerMemberMin();
                            }
                            this.syncGroupAmounts();
                        }
                    });
                    this.$watch('steps', () => this.syncStepKey());
                    this.$watch('form.requested_amount', () => {
                        if (this.phase === 'application') this.scheduleDraftSave();
                    });
                    this.$watch('form.requested_tenure_months', () => {
                        if (this.phase === 'application') {
                            this.updateQuote();
                            this.scheduleDraftSave();
                        }
                    });
                    this.$watch('form.purpose', (value, oldValue) => {
                        const next = this.normalizePurposeKey(value);
                        if (next && next !== value) {
                            this.form.purpose = next;
                            return;
                        }
                        // Keep the editor open for "other" so the custom text field stays visible.
                        if (next && next !== oldValue && ! this.isOtherPurpose(next)) {
                            this.purposeEditing = false;
                        } else if (this.purposeNeedsDetail()) {
                            this.purposeEditing = true;
                        }
                        this.syncPurposeHidden();
                        if (this.phase === 'application') this.scheduleDraftSave();
                    });
                    this.$watch('group.purpose', (value, oldValue) => {
                        const next = this.normalizePurposeKey(value);
                        if (next && next !== value) {
                            this.group.purpose = next;
                            return;
                        }
                        if (next && next !== oldValue && ! this.isOtherPurpose(next)) {
                            this.purposeEditing = false;
                            this.form.purpose = next;
                        } else if (this.isOtherPurpose(next)) {
                            this.form.purpose = next;
                            this.purposeEditing = this.purposeNeedsDetail();
                        }
                        this.syncPurposeHidden();
                        if (this.phase === 'application') this.scheduleDraftSave();
                    });
                    this.$watch('form.guarantor_mode', (mode) => {
                        if (mode === 'external') {
                            this.scheduleExternalInvitePrep();
                        } else if (mode === 'internal') {
                            this.guarantorLookup = { ok: false, label: '', error: '', memberKey: '', phone: '', name: '' };
                        }
                    });
                    this.syncStepKey();
                    if (this.openProfileGateOnLoad && ! this.canApply) {
                        this.$nextTick(() => {
                            this.showProfileGateModal = true;
                        });
                    }
                    if (this.openProfileReadyOnLoad && this.canApply) {
                        this.$nextTick(() => {
                            this.showProfileReadyModal = true;
                        });
                    }
                    if (this.reservationMode && this.assetApplication) {
                        this.beginReservationApplication();
                        return;
                    }
                    if (config.savedDraft) {
                        this.restoreDraft(config.savedDraft);
                        return;
                    }
                    if (config.isResume) {
                        window.location.href = config.loansUrl || '/borrower/loans';
                        return;
                    }
                    if (config.preselect) {
                        const p = this.products.find(x => x.id == config.preselect);
                        if (p) this.openProduct(p);
                        return;
                    }
                    window.location.href = this.loanProductsUrl || '/borrower/loan-products';
                },

                scheduleDraftSave() {
                    clearTimeout(this.draftSaveTimer);
                    this.draftSaveTimer = setTimeout(() => this.persistDraft(), 900);
                },

                persistDeclaration() {
                    clearTimeout(this.declarationSaveTimer);
                    this.declarationSaveTimer = setTimeout(() => this.persistDraft(true), 250);
                },

                buildDraftPayload() {
                    const inputs = {};
                    if (this.phase === 'application') {
                        const form = this.formRoot();
                        if (form) {
                            const fd = new FormData(form);
                            for (const [key, value] of fd.entries()) {
                                if (value instanceof File) continue;
                                if (key === 'signature_data' || key === 'signer_name') continue;
                                // Hidden purpose field is empty until submit sync — never let it
                                // overwrite the Alpine form.purpose value in the draft.
                                if (key === 'purpose') continue;
                                inputs[key] = value;
                            }
                        }
                    }
                    if (this.form.purpose) {
                        inputs.purpose = this.form.purpose;
                    }
                    return {
                        phase: this.phase,
                        step: this.step,
                        step_key: this.stepKey,
                        application_started: this.phase === 'application',
                        loan_product_id: this.form.loan_product_id,
                        asset_reservation_id: this.reservationId,
                        form: this.form,
                        inputs,
                        guarantor_lookup: this.guarantorLookup.ok ? this.guarantorLookup : null,
                        application_fee: this.supplementMode
                            ? (this.applicationFeeState || { status: 'waived', amount: 0 })
                            : this.applicationFeeState,
                        valuation_fee: this.valuationFeeState,
                        asset_documents: this.assetDocuments,
                        external_guarantor: this.externalGuarantor,
                        internal_guarantor: this.internalGuarantor,
                        borrower_signature: this.borrowerSignature,
                        declaration_accepted: this.declarationAccepted,
                        group: this.group,
                        supplement_mode: !!this.supplementMode,
                        supplement_application_id: this.supplementApplicationId || null,
                    };
                },

                feeAmount() {
                    return Number(this.applicationFee) || 0;
                },

                effectiveFeeAmount() {
                    if (this.feeQuoteData) {
                        const due = Number(this.feeQuoteData.cash_due ?? this.feeQuoteData.after_discount);
                        if (due > 0) return due;
                    }
                    const fromQuote = this.feeAmount();
                    const fromProduct = Number(this.current?.application_fee) || 0;
                    const fromReadiness = Number(this.readiness?.fees?.application) || 0;
                    return Math.max(fromQuote, fromProduct, fromReadiness);
                },

                showsApplicationFeePayment() {
                    return ! this.applicationFeePaid && this.effectiveFeeAmount() > 0;
                },

                enterApplicationFeeStep() {
                    const amount = this.effectiveFeeAmount();
                    if (amount > 0 && this.feeAmount() < amount) {
                        this.applicationFee = amount;
                    }
                    this.syncFeePaidState();
                    this.syncValuationFeePaidState();
                    this.refreshApplicationFeeQuote();
                },

                syncFeePaidState() {
                    if (this.supplementMode) {
                        this.applicationFeePaid = true;
                        return;
                    }
                    const amount = this.effectiveFeeAmount();
                    const st = this.applicationFeeState?.status || '';
                    if (amount > 0 && st === 'waived' && ! this.applicationFeeState?.reference) {
                        this.applicationFeeState = null;
                    }
                    const status = this.applicationFeeState?.status || '';
                    this.applicationFeePaid = amount <= 0
                        || ['paid', 'waived'].includes(status);
                },

                feeGateSatisfied() {
                    return this.effectiveFeeAmount() <= 0
                        || ['paid', 'waived'].includes(this.applicationFeeState?.status || '');
                },

                /** Fee is a gate between setup steps and guarantor/review — not a numbered wizard step. */
                needsFeeGateBefore(nextKey) {
                    if (this.isEditHop()) return false;
                    if (this.supplementMode || this.feeGateSatisfied()) return false;
                    if (this.effectiveFeeAmount() <= 0) return false;
                    return ['guarantor', 'product_questions', 'review', 'signature', 'submit'].includes(nextKey);
                },

                feeGateRequiredForStep(targetStepKey) {
                    return this.needsFeeGateBefore(targetStepKey);
                },

                /** Editing amount/guarantor from loan profile — skip the full process. */
                isEditHop() {
                    return this.returnTo === 'profile'
                        || ['quote', 'asset_details', 'asset_tenure', 'group_setup', 'guarantor'].includes(this.returnTo);
                },

                enforceStepRequirements(onResume = false) {
                    if (this.supplementMode || this.isEditHop()) {
                        this.feeGateOpen = false;
                        return;
                    }
                    if (this.effectiveFeeAmount() <= 0 || this.feeGateSatisfied()) {
                        this.feeGateOpen = false;
                        // Do NOT auto-skip setup steps (asset_details / quote / etc.) just because
                        // the fee is zero or already paid — that jumped asset-backed apps to guarantor.
                        return;
                    }
                    if (onResume && ['processing', 'pending'].includes(this.applicationFeeState?.status || '')) {
                        return;
                    }
                    if (this.needsFeeGateBefore(this.stepKey)) {
                        this.feeGateOpen = true;
                        this.enterApplicationFeeStep();
                    }
                },

                isPreFeeSetupStep(key) {
                    return ['quote', 'asset_details', 'asset_tenure', 'group_setup', 'group_members', 'application_fee'].includes(key);
                },

                /** Next numbered wizard stage after the fee gate — works for every product plan. */
                nextStepKeyAfterFee() {
                    const setupKeys = ['quote', 'asset_details', 'asset_tenure', 'group_setup', 'group_members'];
                    const steps = this.steps || [];
                    let lastSetup = -1;
                    steps.forEach((step, index) => {
                        if (setupKeys.includes(step.key)) lastSetup = index;
                    });
                    if (lastSetup >= 0 && steps[lastSetup + 1]?.key) {
                        return steps[lastSetup + 1].key;
                    }
                    const afterSetup = steps.find(s => s.key && ! setupKeys.includes(s.key));
                    return afterSetup?.key || 'review';
                },

                goToStepKey(key) {
                    if (! key) return;
                    this.rebuildSteps(key);
                    const idx = this.resolveStepIndex(key, 0);
                    this.step = idx;
                    this.furthestStep = Math.max(this.furthestStep || 0, idx);
                    this.syncStepKey();
                    this.feeGateOpen = false;
                },

                openAddMemberPanel() {
                    this.addMemberOpen = true;
                    this.groupLookupError = '';
                    this.groupMemberLookup = { ok: false, label: '', error: '', data: null };
                    this.groupExternalInvite = null;
                    if (! this.groupMemberMode) this.groupMemberMode = 'internal';
                },

                closeAddMemberPanel() {
                    this.addMemberOpen = false;
                    this.groupLookupError = '';
                    this.groupMemberLookup = { ok: false, label: '', error: '', data: null };
                    this.groupExternalInvite = null;
                    this.groupExternal = { first_name: '', last_name: '', phone: '' };
                },

                syncQuoteFormFromDom() {
                    // Alpine form.purpose is authoritative. The hidden name="purpose" field
                    // often still holds a stale value (e.g. "other") from an earlier sync and
                    // must not overwrite a newer pick when validating Continue.
                    if (! this.form.purpose) {
                        const purpose = this.readFormField('purpose');
                        if (purpose) this.form.purpose = purpose;
                    }
                    this.syncPurposeHidden();
                },

                setLoanPurpose(value) {
                    const next = this.normalizePurposeKey(value);
                    this.form.purpose = next;
                    if (next && ! this.isOtherPurpose(next)) {
                        this.form.purpose_other = '';
                        this.purposeEditing = false;
                    } else if (this.isOtherPurpose(next)) {
                        // Keep the free-text field open until they describe the purpose.
                        this.purposeEditing = true;
                    }
                    this.syncPurposeHidden();
                    this.scheduleDraftSave();
                },

                setGroupPurpose(value) {
                    const next = this.normalizePurposeKey(value);
                    this.group.purpose = next;
                    this.form.purpose = next;
                    if (next && ! this.isOtherPurpose(next)) {
                        this.form.purpose_other = '';
                        this.purposeEditing = false;
                    } else if (this.isOtherPurpose(next)) {
                        this.purposeEditing = true;
                    }
                    this.syncPurposeHidden();
                    this.scheduleDraftSave();
                },

                normalizePurposeKey(value) {
                    const raw = String(value || '').trim();
                    if (! raw) return '';
                    const labels = this.purposeLabels || {};
                    if (Object.prototype.hasOwnProperty.call(labels, raw)) {
                        return raw;
                    }
                    const match = Object.entries(labels).find(([, label]) => String(label) === raw);
                    return match ? match[0] : raw;
                },

                isOtherPurpose(value = null) {
                    const key = this.normalizePurposeKey(value ?? this.form.purpose);
                    if (key === 'other') return true;
                    const label = this.purposeLabels?.other;
                    return !!(label && String(value ?? this.form.purpose) === String(label));
                },

                purposeNeedsDetail() {
                    const purpose = this.isGroupProduct(this.current)
                        ? (this.group.purpose || this.form.purpose)
                        : this.form.purpose;
                    return this.isOtherPurpose(purpose)
                        && ! String(this.form.purpose_other || '').trim();
                },

                syncPurposeHidden() {
                    const el = this.formRoot()?.querySelector('[data-submit-purpose]');
                    if (el) {
                        el.value = this.normalizePurposeKey(this.form.purpose) || '';
                    }
                    const otherEl = this.formRoot()?.querySelector('[data-submit-purpose-other]');
                    if (otherEl) {
                        otherEl.value = this.isOtherPurpose(this.form.purpose)
                            ? String(this.form.purpose_other || '').trim()
                            : '';
                    }
                },

                async autoWaiveApplicationFeeIfNeeded() {
                    if (this.effectiveFeeAmount() > 0 || this.applicationFeePaid) return;
                    this.applicationFeeState = {
                        status: 'waived',
                        reference: null,
                        channel: 'waived',
                        amount: 0,
                        paid_at: new Date().toISOString(),
                    };
                    this.syncFeePaidState();
                    await this.persistDraft(true);
                },

                isAssetBackedProduct(product) {
                    return (product?.code || '').toUpperCase() === 'AB';
                },

                effectiveValuationFeeAmount() {
                    return Number(this.valuationFeeAmount) || 0;
                },

                showsValuationFeePayment() {
                    return ! this.valuationFeePaid && this.effectiveValuationFeeAmount() > 0;
                },

                enterValuationFeeStep() {
                    this.syncValuationFeePaidState();
                    this.refreshValuationFeeQuote();
                },

                syncValuationFeePaidState() {
                    const amount = this.effectiveValuationFeeAmount();
                    const st = this.valuationFeeState?.status || '';
                    if (amount > 0 && st === 'waived' && ! this.valuationFeeState?.reference) {
                        this.valuationFeeState = null;
                    }
                    const status = this.valuationFeeState?.status || '';
                    this.valuationFeePaid = amount <= 0 || ['paid', 'waived'].includes(status);
                },

                valuationFeeGateSatisfied() {
                    if (! this.hasStep('valuation_fee')) return true;
                    return this.effectiveValuationFeeAmount() <= 0
                        || ['paid', 'waived'].includes(this.valuationFeeState?.status || '');
                },

                valuationGateRequiredForStep(targetStepKey) {
                    const feeIdx = this.steps.findIndex(s => s.key === 'valuation_fee');
                    const targetIdx = this.steps.findIndex(s => s.key === targetStepKey);
                    return feeIdx >= 0 && targetIdx > feeIdx && this.effectiveValuationFeeAmount() > 0;
                },

                async refreshValuationFeeQuote() {
                    if (! this.form.loan_product_id || ! this.valuationFeeQuoteUrl) {
                        this.syncValuationFeePaidState();
                        return;
                    }
                    try {
                        const url = `${this.valuationFeeQuoteUrl}?loan_product_id=${encodeURIComponent(this.form.loan_product_id)}`;
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (! res.ok) return;
                        const data = await res.json();
                        if (data.amount !== undefined) {
                            this.valuationFeeAmount = data.amount;
                        }
                    } catch (e) {
                        console.warn('valuation fee quote failed', e);
                    } finally {
                        this.syncValuationFeePaidState();
                    }
                },

                async payValuationFee() {
                    if (! this.valuationFeePayUrl || ! this.form.loan_product_id) return;
                    if (! this.form.asset_type) {
                        showWizardFeedback(this.i18n.assetDetails.typeRequired);
                        return;
                    }
                    this.valuationFeePaying = true;
                    try {
                        const body = {
                            loan_product_id: this.form.loan_product_id,
                            channel: this.valuationFeeChannel || 'mobile_money',
                            payment_phone: this.valuationFeePhone || '',
                            asset_type: this.form.asset_type,
                            asset_description: this.form.asset_description || '',
                        };
                        const res = await fetch(this.valuationFeePayUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(body),
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            throw new Error(data.message || this.i18n.valuationFee.failed);
                        }
                        if (data.wait_url) {
                            window.location.href = data.wait_url;
                            return;
                        }
                        this.valuationFeeState = data.fee;
                        this.syncValuationFeePaidState();
                        await this.persistDraft(true);
                        showWizardFeedback(data.message || this.i18n.valuationFee.paid);
                    } catch (e) {
                        showWizardFeedback(e?.message || this.i18n.valuationFee.failed);
                    } finally {
                        this.valuationFeePaying = false;
                    }
                },

                applyExistingAsset() {
                    const ids = this.selectedCustomerAssetIds();
                    if (! ids.length) return;
                    const primaryId = String(ids[0]);
                    const asset = (this.customerAssets || []).find(a => String(a.id) === primaryId);
                    if (! asset) return;
                    this.form.customer_asset_id = asset.id;
                    this.form.asset_type = asset.asset_type || this.form.asset_type;
                    this.form.asset_description = asset.description || asset.label || this.form.asset_description;
                    this.scheduleDraftSave();
                },

                selectedCustomerAssetIds() {
                    const raw = this.form.customer_asset_ids;
                    if (Array.isArray(raw) && raw.length) {
                        return raw.map(String);
                    }
                    if (this.form.customer_asset_id) {
                        return [String(this.form.customer_asset_id)];
                    }
                    return [];
                },

                isCustomerAssetSelected(id) {
                    return this.selectedCustomerAssetIds().includes(String(id));
                },

                profileCompletionPercent() {
                    const sections = this.profileSections || [];
                    if (! sections.length) return 100;
                    const done = sections.filter(s => s && s.complete).length;
                    return Math.round((done / sections.length) * 100);
                },

                profileIncompleteCount() {
                    const sections = this.profileSections || [];
                    return sections.filter(s => s && ! s.complete).length;
                },

                toggleCustomerAsset(id) {
                    const key = String(id);
                    let ids = this.selectedCustomerAssetIds().slice();
                    if (ids.includes(key)) {
                        ids = ids.filter(v => v !== key);
                    } else {
                        ids.push(key);
                    }
                    this.form.customer_asset_ids = ids.map(v => Number(v) || v);
                    this.form.customer_asset_id = ids[0] || '';
                    this.applyExistingAsset();
                },

                selectedCustomerAsset() {
                    const id = String(this.form.customer_asset_id || this.selectedCustomerAssetIds()[0] || '');
                    if (! id) return null;
                    return (this.customerAssets || []).find(a => String(a.id) === id) || null;
                },

                async uploadAssetDocument(code, event) {
                    const file = event.target?.files?.[0];
                    if (! file || ! this.assetDocumentUploadUrl || ! this.form.loan_product_id) return;
                    this.assetDocumentUploading = true;
                    try {
                        const formData = new FormData();
                        formData.append('loan_product_id', this.form.loan_product_id);
                        formData.append('document_code', code);
                        formData.append('file', file);
                        const res = await fetch(this.assetDocumentUploadUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: formData,
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            throw new Error(data.message || this.i18n.assetDetails.uploadFailed);
                        }
                        this.assetDocuments = data.asset_documents || {};
                        await this.persistDraft(true);
                    } catch (e) {
                        showWizardFeedback(e?.message || this.i18n.assetDetails.uploadFailed);
                    } finally {
                        this.assetDocumentUploading = false;
                        if (event.target) event.target.value = '';
                    }
                },

                async refreshApplicationFeeQuote() {
                    if (! this.form.loan_product_id) return;
                    if (this.current?.application_fee > 0) {
                        this.applicationFee = this.current.application_fee;
                    }
                    if (! this.applicationFeeQuoteUrl) {
                        this.syncFeePaidState();
                        return;
                    }
                    try {
                        const params = new URLSearchParams({
                            loan_product_id: String(this.form.loan_product_id),
                            use_wallet: this.feeUseWallet ? '1' : '0',
                        });
                        if (this.feePromoCode) {
                            const code = String(this.feePromoCode).trim().toUpperCase();
                            params.set('promo_code', code);
                            params.set('affiliate_code', code);
                        }
                        if (this.isGroupProduct(this.current)) {
                            params.set('member_count', String(Math.max(1, this.groupTargetCount())));
                        }
                        const url = `${this.applicationFeeQuoteUrl}?${params.toString()}`;
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (! res.ok) return;
                        const data = await res.json();
                        if (data.amount !== undefined) {
                            this.applicationFee = data.amount;
                        }
                        if (data.quote) {
                            this.feeQuoteData = data.quote;
                        }
                        if (data.breakdown) {
                            this.groupFeeBreakdownData = data.breakdown;
                        }
                    } catch (e) {
                        console.warn('application fee quote failed', e);
                    } finally {
                        this.syncFeePaidState();
                        // Quote may arrive after resume landed on guarantor — open the same IL fee gate.
                        if (! this.supplementMode && ! this.isEditHop()
                            && ! this.feeGateSatisfied()
                            && this.needsFeeGateBefore(this.stepKey)) {
                            this.feeGateOpen = true;
                            this.enterApplicationFeeStep();
                        }
                    }
                },

                estimatedLoyaltySave() {
                    const option = this.feeLoyaltyOption;
                    if (! option || ! this.feeRedeemLoyalty) return 0;
                    const base = Number(this.feeQuoteData?.base ?? this.applicationFee ?? 0);
                    if (base <= 0) return 0;
                    if (option.benefit_type === 'fixed_discount') {
                        return Math.min(base, Number(option.benefit_value || 0));
                    }
                    return Math.round(base * (Number(option.benefit_value || 0) / 100));
                },

                async payApplicationFee() {
                    if (! this.applicationFeePayUrl || ! this.form.loan_product_id) return;
                    this.feePaying = true;
                    this.feeNotice = null;
                    try {
                        // Persist group roster size before opening payments.show so fee × members is locked.
                        await this.persistDraft(true);
                        await this.refreshApplicationFeeQuote();
                        const feeCode = this.feePromoCode
                            ? String(this.feePromoCode).trim().toUpperCase()
                            : null;
                        const body = {
                            loan_product_id: this.form.loan_product_id,
                            payment_phone: this.feePhone || '',
                            use_wallet: !!this.feeUseWallet,
                            promo_code: feeCode,
                            affiliate_code: feeCode,
                            redeem_loyalty: !!(this.feeRedeemLoyalty && this.feeLoyaltyOption?.can_redeem),
                            loyalty_option_key: this.feeLoyaltyOption?.key || null,
                            member_count: this.isGroupProduct(this.current)
                                ? Math.max(1, this.groupTargetCount())
                                : undefined,
                        };
                        const res = await fetch(this.applicationFeePayUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(body),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.ok) {
                            throw new Error(data.message || this.i18n.applicationFee.failed);
                        }
                        // Shared payments.show owns method selection + USSD push.
                        if (data.wait_url) {
                            window.location.href = data.wait_url;
                            return;
                        }
                        this.applicationFeeState = data.fee;
                        if (this.isAssetBackedProduct(this.current) && data.fee) {
                            this.valuationFeeState = data.fee;
                        }
                        if (data.fee_loyalty_option === null || data.loyalty_redeemed) {
                            this.feeLoyaltyOption = null;
                            this.feeRedeemLoyalty = false;
                        }
                        this.syncFeePaidState();
                        this.syncValuationFeePaidState();
                        await this.persistDraft(true);
                        this.feeGateOpen = false;
                        this.rebuildSteps();
                        this.feeNotice = {
                            tone: 'success',
                            message: data.message || this.i18n.applicationFee.paid,
                        };
                        this.celebratePayment(
                            this.i18n.applicationFee?.celebrate_title || 'Fee paid — continue',
                            data.message || this.i18n.applicationFee.paid
                        );
                        this.feePaying = false;
                        try {
                            await this.next();
                        } catch (advanceErr) {
                            console.warn('advance after fee pay failed', advanceErr);
                        }
                        return;
                    } catch (e) {
                        this.feeNotice = {
                            tone: 'error',
                            message: e?.message || this.i18n.applicationFee.failed,
                        };
                    } finally {
                        this.feePaying = false;
                    }
                },

                celebratePayment(title, message) {
                    try {
                        window.dispatchEvent(new CustomEvent('open-feedback-default', {
                            detail: {
                                tone: 'success',
                                title: title || 'Congratulations',
                                message: message || '',
                            },
                        }));
                    } catch (e) {}
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
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
                        const duration = 2200 + Math.random() * 1400;
                        const size = 5 + Math.random() * 7;
                        const isRound = Math.random() > 0.55;
                        piece.style.cssText = [
                            'position:fixed',
                            `top:${originY}px`,
                            `left:${originX}px`,
                            `width:${size}px`,
                            `height:${isRound ? size : size * (1.2 + Math.random())}px`,
                            `background:${colors[i % colors.length]}`,
                            'opacity:1',
                            'z-index:9999',
                            `border-radius:${isRound ? '999px' : '2px'}`,
                            'pointer-events:none',
                            `transform:translate(-50%,-50%) rotate(${Math.random() * 360}deg)`,
                            `transition:transform ${duration}ms cubic-bezier(0.15,0.75,0.25,1), opacity ${duration}ms ease-out`,
                        ].join(';');
                        document.body.appendChild(piece);
                        setTimeout(() => {
                            piece.style.transform = `translate(calc(-50% + ${driftX}px), calc(-50% + ${driftY + window.innerHeight * 0.55}px)) rotate(${Math.random() * 720}deg)`;
                            piece.style.opacity = '0';
                        }, delay);
                        setTimeout(() => piece.remove(), delay + duration + 80);
                    }
                },

                clearSavedDraft() {
                    if (! this.draftSaveUrl) return Promise.resolve();
                    return fetch(this.draftSaveUrl, {
                        method: 'PUT',
                        headers: this.draftHeaders(),
                        credentials: 'same-origin',
                        body: JSON.stringify({ phase: 'browse' }),
                    }).catch(() => {});
                },

                persistDraft(sync = false) {
                    // Guarantor supplement must never recreate a product draft (that resurfaces
                    // as "payment required" after the application fee was already paid).
                    if (this.supplementMode) {
                        return Promise.resolve();
                    }
                    if (! this.draftSaveUrl || this.phase === 'browse' || this.resumeLoading) {
                        return Promise.resolve();
                    }
                    const request = () => {
                        let payload;
                        try {
                            payload = this.buildDraftPayload();
                        } catch (e) {
                            console.warn('apply wizard draft payload failed', e);
                            payload = {
                                phase: this.phase,
                                step: this.step,
                                step_key: this.stepKey,
                                loan_product_id: this.form.loan_product_id,
                                asset_reservation_id: this.reservationId,
                                form: this.form,
                                inputs: {},
                                guarantor_lookup: this.guarantorLookup.ok ? this.guarantorLookup : null,
                                application_fee: this.applicationFeeState,
                                external_guarantor: this.externalGuarantor,
                        internal_guarantor: this.internalGuarantor,
                                borrower_signature: this.borrowerSignature,
                                declaration_accepted: this.declarationAccepted,
                            };
                        }
                        return fetch(this.draftSaveUrl, {
                            method: 'PUT',
                            headers: this.draftHeaders(),
                            credentials: 'same-origin',
                            body: JSON.stringify(payload),
                        }).then(res => res.ok ? res.json() : Promise.reject(res))
                          .then((data) => {
                              this.draftSavedAt = new Date().toLocaleTimeString();
                              if (data?.draft_reference) {
                                  this.draftReference = data.draft_reference;
                              }
                          });
                    };

                    return sync ? request().catch(() => {}) : request().catch(() => {});
                },

                draftHeaders() {
                    return {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    };
                },

                restoreFormInputs(inputs) {
                    const root = this.formRoot();
                    if (! root) return;
                    Object.entries(inputs || {}).forEach(([name, value]) => {
                        if (name === 'purpose' && ! String(value || '').trim()) return;
                        const el = root.querySelector(`[name="${name}"]`);
                        if (! el || el.type === 'file') return;
                        if (el.type === 'radio') {
                            const radio = root.querySelector(`[name="${name}"][value="${value}"]`);
                            if (radio) radio.checked = true;
                        } else {
                            el.value = value;
                        }
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                },

                restoreDraft(draft) {
                    const product = this.products.find(p => p.id == draft.loan_product_id);
                    if (! product) {
                        if (this.isResume && config.loansUrl) {
                            window.location.href = config.loansUrl;
                        }
                        return false;
                    }

                    const target = draft.resume_target || {};
                    this.resumeLoading = true;
                    this.current = product;
                    this.form.loan_product_id = product.id;
                    this.phase = target.phase === 'application' ? 'application' : 'details';
                    this.selectProduct(product, false);

                    Object.assign(this.form, draft.form || {});
                    if (this.form.purpose) {
                        this.form.purpose = this.normalizePurposeKey(this.form.purpose);
                    }
                    this.purposeEditing = this.purposeNeedsDetail();
                    if (draft.inputs) {
                        this.restoreFormInputs(draft.inputs);
                        [
                            'external_first_name', 'external_middle_name', 'external_last_name',
                            'external_relationship', 'external_phone', 'external_email',
                            'external_region', 'external_district',
                            'internal_member_no', 'internal_guarantor_phone', 'internal_guarantor_name',
                        ].forEach((key) => {
                            if (draft.inputs[key]) this.form[key] = draft.inputs[key];
                        });
                        // Prefer Alpine form.purpose; only fill from inputs when still empty.
                        if (! this.form.purpose && draft.inputs.purpose) {
                            this.form.purpose = draft.inputs.purpose;
                        }
                    }
                    this.syncGuarantorFormFromDom();
                    if (this.form.guarantor_mode === 'external') {
                        this.scheduleExternalInvitePrep();
                    }
                    if (draft.guarantor_lookup) this.guarantorLookup = draft.guarantor_lookup;
                    if (draft.application_fee) this.applicationFeeState = draft.application_fee;
                    if (draft.valuation_fee) this.valuationFeeState = draft.valuation_fee;
                    if (draft.asset_documents) this.assetDocuments = draft.asset_documents;
                    if (draft.external_guarantor) this.externalGuarantor = draft.external_guarantor;
                    if (draft.internal_guarantor) this.internalGuarantor = draft.internal_guarantor;
                    if (draft.borrower_signature) this.borrowerSignature = draft.borrower_signature;
                    if (draft.declaration_accepted || draft.borrower_signature) this.declarationAccepted = true;
                    if (draft.group) {
                        this.group = {
                            name: '',
                            purpose: '',
                            target_member_count: null,
                            amount_per_member: 0,
                            members: [],
                            ...draft.group,
                            members: Array.isArray(draft.group.members) ? draft.group.members : [],
                        };
                    }
                    if (draft.draft_reference) this.draftReference = draft.draft_reference;
                    this.syncFeePaidState();
                    this.syncValuationFeePaidState();

                    const resumeStep = target.step ?? draft.step ?? 0;
                    const resumeKey = target.step_key ?? draft.step_key ?? '';

                    return this.loadReadiness(product.id).then(() => {
                        this.phase = target.phase === 'application' || target.phase === 'details'
                            ? target.phase
                            : 'application';
                        if (this.phase !== 'application') {
                            return true;
                        }

                        this.phase = 'application';
                        this.rebuildSteps(resumeKey);
                        const viewStep = this.resolveStepIndex(resumeKey, resumeStep);
                        const savedKey = draft.step_key || '';
                        const savedStep = this.resolveStepIndex(savedKey, draft.step ?? resumeStep ?? 0);
                        let furthest = Math.max(viewStep, savedStep, Number(draft.step) || 0);
                        // Do not unlock Review/Submit just because a signature exists —
                        // that makes locale reloads feel like a jump off Guarantor.
                        this.furthestStep = furthest;
                        this.step = viewStep;
                        this.updateQuote();
                        this.syncStepKey();
                        this.clampToIncompleteSetup();
                        this.enforceStepRequirements(this.isResume);
                        if (this.stepKey === 'review' || this.stepKey === 'signature' || this.stepKey === 'submit') {
                            this.refreshReview(this.formRoot());
                        }
                        if (this.stepKey === 'signature') {
                            this.$nextTick(() => this.restoreSignaturePad());
                        }
                        if (this.stepKey === 'submit') {
                            this.$nextTick(() => this.syncSubmitPayload(this.formRoot()));
                        }
                        return true;
                    }).finally(() => {
                        this.resumeLoading = false;
                        this.scrollWizardIntoView();
                    });
                },

                isMarketplaceProduct(product) {
                    const code = (product?.code || '').toUpperCase();
                    return this.marketplaceOnlyCodes.map(c => c.toUpperCase()).includes(code);
                },

                isGroupProduct(product) {
                    if (! product) return false;
                    if (product.is_group) return true;
                    const code = (product.code || '').toUpperCase();
                    return code === 'GL';
                },

                initGroupLeader() {
                    if (! this.leaderCustomerId) return;
                    if (! this.group.target_member_count) {
                        this.group.target_member_count = this.groupLimits.min;
                    }
                    if (! this.group.amount_per_member) {
                        this.group.amount_per_member = this.groupAmountPerMemberMin();
                    } else {
                        this.clampGroupAmountPerMember();
                    }
                    const exists = this.group.members.some(m => Number(m.customer_id) === Number(this.leaderCustomerId));
                    if (exists) {
                        this.group.members = this.group.members.map((m) => {
                            if (Number(m.customer_id) !== Number(this.leaderCustomerId)) return m;
                            return {
                                ...m,
                                role: 'leader',
                                avatar_url: m.avatar_url || this.leaderAvatarUrl || null,
                            };
                        });
                        this.syncGroupAmounts();
                        return;
                    }
                    this.group.members = [{
                        customer_id: this.leaderCustomerId,
                        name: this.leaderName,
                        phone: this.leaderPhone,
                        role: 'leader',
                        requested_amount: this.group.amount_per_member,
                        avatar_url: this.leaderAvatarUrl || null,
                    }];
                    this.syncGroupAmounts();
                },

                groupTargetCount() {
                    return Number(this.group.target_member_count || this.groupLimits.min || 0);
                },

                groupAmountPerMemberMin() {
                    const configured = Number(this.groupLimits?.minAmountPerMember || 200000);
                    const totalMin = Number(this.current?.min || configured);
                    const members = Math.max(this.groupLimits.min, this.groupTargetCount() || this.groupLimits.min);
                    return Math.max(configured, Math.ceil(totalMin / Math.max(1, members)));
                },

                groupAmountPerMemberMax() {
                    const totalMax = Number(this.current?.max || 5000000);
                    const members = Math.max(this.groupLimits.min, this.groupTargetCount() || this.groupLimits.min);
                    return Math.max(this.groupAmountPerMemberMin(), Math.floor(totalMax / members));
                },

                clampGroupAmountPerMember() {
                    const min = this.groupAmountPerMemberMin();
                    const max = this.groupAmountPerMemberMax();
                    const value = Number(this.group.amount_per_member || min);
                    this.group.amount_per_member = Math.min(max, Math.max(min, value));
                },

                groupTotalAmount() {
                    const count = this.groupTargetCount();
                    const perMember = Number(this.group.amount_per_member || 0);
                    return count * perMember;
                },

                groupFeeBreakdown() {
                    if (this.groupFeeBreakdownData) {
                        return this.groupFeeBreakdownData;
                    }
                    const perMember = Number(this.current?.application_fee || 0);
                    const count = this.groupTargetCount();
                    return {
                        per_member: perMember,
                        member_count: count,
                        total: perMember * count,
                    };
                },

                selectGroupTenure(months) {
                    this.form.requested_tenure_months = Number(months);
                    this.updateQuote();
                },

                groupTenureOptionIndex() {
                    const options = this.current?.tenure_options || [];
                    if (! options.length) return 0;
                    const idx = options.findIndex((months) => Number(months) === Number(this.form.requested_tenure_months));
                    return idx >= 0 ? idx : 0;
                },

                selectGroupTenureByIndex(index) {
                    const options = this.current?.tenure_options || [];
                    const months = options[Number(index)];
                    if (months == null) return;
                    this.selectGroupTenure(months);
                },

                syncGroupAmounts() {
                    this.clampGroupAmountPerMember();
                    const perMember = Number(this.group.amount_per_member || 0);
                    this.group.members = this.group.members.map((member) => ({
                        ...member,
                        requested_amount: perMember,
                    }));
                    this.form.requested_amount = this.group.members.reduce((sum, m) => sum + Number(m.requested_amount || 0), 0);
                    if (this.group.purpose) this.form.purpose = this.group.purpose;
                    this.updateQuote();
                },

                groupProgress() {
                    if (this.groupProgressSummary) {
                        return this.groupProgressSummary;
                    }
                    const target = this.groupTargetCount();
                    const active = this.group.members.filter(m => !['declined', 'expired'].includes(m.status_key || ''));
                    const added = active.length;
                    const verified = active.filter(m => (m.status_key || '') === 'kyc_complete').length;
                    const profiles = active.filter(m => ['profile_complete', 'kyc_complete'].includes(m.status_key || '')).length;
                    const awaitingAcceptance = active.filter(m => [
                        'invitation_sent', 'link_opened', 'pending_invitation',
                    ].includes(m.status_key || (m.invitation_id && m.role !== 'leader' ? 'invitation_sent' : ''))).length;
                    const invitationsPending = active.filter(m => [
                        'invitation_sent', 'link_opened', 'pending_invitation', 'registration_started',
                        'registration_complete', 'account_registered', 'profile_incomplete',
                    ].includes(m.status_key || (m.invitation_id && m.role !== 'leader' ? 'invitation_sent' : ''))).length;
                    const tpl = this.i18n.groupProgress || {};
                    const fill = (text, vars) => Object.entries(vars).reduce((s, [k, v]) => s.replace(':' + k, String(v)), text || '');
                    const canContinue = target > 0 && added === target && awaitingAcceptance === 0;
                    const avg = added > 0
                        ? Math.round(active.reduce((sum, m) => sum + Number(m.profile_percent || 0), 0) / added)
                        : 0;
                    return {
                        target,
                        added,
                        verified,
                        profiles_complete: profiles,
                        avg_profile_percent: avg,
                        awaiting_acceptance: awaitingAcceptance,
                        invitations_pending: invitationsPending,
                        pending: Math.max(0, target - added),
                        summary: [
                            fill(tpl.added, { added, target }),
                            fill(tpl.profiles, { done: profiles, target }),
                            fill(tpl.avg_completion, { percent: avg }),
                            fill(tpl.invitations_pending, { count: invitationsPending }),
                        ],
                        can_continue: canContinue,
                        can_submit: canContinue && profiles === target,
                    };
                },

                memberStatusLabel(member) {
                    const key = member.status_key || (member.invitation_id ? 'invitation_sent' : 'profile_incomplete');
                    return this.groupProgressLabels?.[key] || key;
                },

                memberStatusClass(member) {
                    const key = member.status_key || (member.invitation_id ? 'invitation_sent' : 'profile_incomplete');
                    if (key === 'kyc_complete' || member.signed) return 'text-emerald-700';
                    if (key === 'awaiting_signature') return 'text-amber-800';
                    return 'text-brand';
                },

                groupRosterPageSize: 5,
                groupRosterPage: 0,
                groupSigSlide: 0,

                groupRosterPages() {
                    const n = (this.group?.members || []).length;
                    return Math.max(1, Math.ceil(n / this.groupRosterPageSize));
                },

                groupRosterPageMembers() {
                    const start = this.groupRosterPage * this.groupRosterPageSize;
                    return (this.group?.members || []).slice(start, start + this.groupRosterPageSize);
                },

                groupRosterAbsoluteIndex(localIndex) {
                    return (this.groupRosterPage * this.groupRosterPageSize) + localIndex;
                },

                groupRosterPrevPage() {
                    this.groupRosterPage = Math.max(0, this.groupRosterPage - 1);
                },

                groupRosterNextPage() {
                    this.groupRosterPage = Math.min(this.groupRosterPages() - 1, this.groupRosterPage + 1);
                },

                groupSigPrev() {
                    const n = (this.group?.members || []).length;
                    if (! n) return;
                    this.groupSigSlide = (this.groupSigSlide - 1 + n) % n;
                },

                groupSigNext() {
                    const n = (this.group?.members || []).length;
                    if (! n) return;
                    this.groupSigSlide = (this.groupSigSlide + 1) % n;
                },

                syncGroupSignatureUi() {
                    const n = (this.group?.members || []).length;
                    const pages = Math.max(1, Math.ceil(n / this.groupRosterPageSize));
                    if (this.groupRosterPage >= pages) this.groupRosterPage = Math.max(0, pages - 1);
                    if (this.groupSigSlide >= n) this.groupSigSlide = Math.max(0, n - 1);
                },

                groupSigCurrentMember() {
                    const members = this.group?.members || [];
                    if (! members.length) return null;
                    return members[this.groupSigSlide] || members[0] || null;
                },

                groupSigIsLeaderSlide() {
                    if (! this.isGroupProduct(this.current)) return true;
                    const member = this.groupSigCurrentMember();
                    return ! member || (member.role || '') === 'leader';
                },

                groupSigSlideName() {
                    if (! this.isGroupProduct(this.current)) {
                        return this.verifiedLegalName || this.borrowerSignature?.signer_name || '—';
                    }
                    const member = this.groupSigCurrentMember();
                    return member?.name || member?.label || member?.phone || this.verifiedLegalName || '—';
                },

                groupScoringRiskBandLabel(band) {
                    return this.i18n.groupScoringRiskBand?.[band] || band || '';
                },

                groupStatusPayload() {
                    return {
                        name: this.group.name || '',
                        purpose: this.group.purpose || '',
                        target_member_count: this.groupTargetCount(),
                    };
                },

                async refreshGroupMemberStatuses() {
                    if (! this.groupMemberStatusesUrl || ! this.group.members.length) return;
                    try {
                        const res = await fetch(this.groupMemberStatusesUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                members: this.group.members,
                                target_member_count: this.groupTargetCount(),
                                group: this.groupStatusPayload(),
                            }),
                        });
                        const data = await res.json();
                        if (res.ok && data.ok) {
                            const beforeCount = this.group.members.length;
                            if (Array.isArray(data.members)) {
                                this.group.members = data.members;
                                this.syncGroupSignatureUi();
                            }
                            if (data.summary) {
                                this.groupProgressSummary = data.summary;
                            }
                            if (data.application_status) {
                                this.groupApplicationStatus = data.application_status;
                            }
                            if (data.scoring) {
                                this.groupScoring = data.scoring;
                            }
                            if (Array.isArray(data.members) && data.members.length < beforeCount) {
                                this.updateGroupTotal();
                                await this.persistDraft(true);
                                if (this.group.members.length < this.groupTargetCount()) {
                                    this.openAddMemberPanel();
                                }
                            }
                        }
                    } catch (e) {
                        // Non-blocking refresh
                    }
                },

                async inviteExternalGroupMember() {
                    if (! this.groupMemberInviteUrl || ! this.current) return;
                    this.groupLookupError = '';
                    this.groupInviteLoading = true;
                    try {
                        const res = await fetch(this.groupMemberInviteUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                loan_product_id: this.current.id,
                                first_name: this.groupExternal.first_name,
                                last_name: this.groupExternal.last_name,
                                phone: this.groupExternal.phone,
                                invitation_reason: this.group.invitation_reason || null,
                                group: {
                                    name: this.group.name,
                                    purpose: this.group.purpose,
                                    amount_per_member: this.group.amount_per_member,
                                    requested_tenure_months: this.form.requested_tenure_months,
                                    target_member_count: this.group.target_member_count,
                                },
                            }),
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            if (data.code === 'already_member') {
                                this.openAlreadyMemberModal(data);
                                return;
                            }
                            this.groupLookupError = data.message || this.i18n.group.lookupNotFound;
                            return;
                        }
                        this.groupExternalInvite = null;
                        this.group.members.push({
                            invitation_id: data.invitation_id || data.share?.invitation_id,
                            name: data.name,
                            phone: data.phone,
                            role: 'member',
                            requested_amount: this.group.amount_per_member,
                            status_key: 'invitation_sent',
                            share: data.share || null,
                            avatar_url: data.avatar_url || null,
                            _open: true,
                        });
                        this.groupExternal = { first_name: '', last_name: '', phone: '' };
                        this.syncGroupAmounts();
                        this.groupProgressSummary = null;
                        this.closeAddMemberPanel();
                        await this.persistDraft(true);
                    } catch (e) {
                        this.groupLookupError = this.i18n.group.lookupNotFound;
                    } finally {
                        this.groupInviteLoading = false;
                    }
                },

                openAlreadyMemberModal(data = {}) {
                    this.groupLookupError = '';
                    this.alreadyMemberModal = {
                        name: data.name || '',
                        phone: String(data.phone || this.groupExternal.phone || '').replace(/\D/g, ''),
                    };
                    this.showAlreadyMemberModal = true;
                },

                dismissAlreadyMemberModal() {
                    this.showAlreadyMemberModal = false;
                    this.alreadyMemberModal = { name: '', phone: '' };
                },

                switchToMemberSearchFromModal() {
                    const phone = String(this.alreadyMemberModal?.phone || this.groupExternal.phone || '').replace(/\D/g, '');
                    this.dismissAlreadyMemberModal();
                    this.groupMemberMode = 'internal';
                    this.groupLookupError = '';
                    this.groupMemberLookup = { ok: false, label: '', error: '', data: null };
                    this.groupLookupPhone = phone;
                    this.groupExternal = { first_name: '', last_name: '', phone: '' };
                    this.addMemberOpen = true;
                },

                updateGroupTotal() {
                    const total = this.group.members.reduce((sum, m) => sum + Number(m.requested_amount || 0), 0);
                    this.form.requested_amount = total;
                    if (this.group.purpose) this.form.purpose = this.group.purpose;
                    this.updateQuote();
                },

                async loadPreviousGroupMembers() {
                    if (! this.previousGroupMembersUrl) return;
                    try {
                        const excludeIds = (this.group?.members || [])
                            .map(m => m.customer_id)
                            .filter(Boolean)
                            .join(',');
                        const url = excludeIds
                            ? `${this.previousGroupMembersUrl}?exclude=${encodeURIComponent(excludeIds)}`
                            : this.previousGroupMembersUrl;
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        const data = await response.json();
                        this.previousGroupMembers = data.members || [];
                    } catch (e) {
                        this.previousGroupMembers = [];
                    }
                },

                async selectPreviousGroupMember(customerId) {
                    if (! this.selectPreviousGroupMemberUrl || ! this.current || ! customerId) return;
                    if (this.group.members.length >= this.groupTargetCount()) return;
                    if (this.group.members.some(m => Number(m.customer_id) === Number(customerId))) {
                        this.groupLookupError = this.i18n.groupMembers.duplicate;
                        return;
                    }
                    this.groupLookupError = '';
                    this.groupLookupLoading = true;
                    try {
                        const res = await fetch(this.selectPreviousGroupMemberUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                customer_id: customerId,
                                loan_product_id: this.current.id,
                            }),
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            this.groupLookupError = data.message || this.i18n.group.lookupNotFound;
                            return;
                        }
                        this.group.members.push({
                            customer_id: data.customer_id,
                            invitation_id: data.invitation_id,
                            name: data.name,
                            phone: data.phone,
                            role: 'member',
                            requested_amount: this.group.amount_per_member,
                            status_key: data.status_key || 'profile_incomplete',
                            avatar_url: data.avatar_url || null,
                        });
                        this.updateGroupTotal();
                        await this.persistDraft(true);
                    } catch (e) {
                        this.groupLookupError = this.i18n.group.lookupNotFound;
                    } finally {
                        this.groupLookupLoading = false;
                    }
                },

                async validateGroupMember() {
                    if (! this.groupMemberLookupUrl) return;
                    this.groupLookupError = '';
                    this.groupMemberLookup = { ok: false, label: '', error: '', data: null };
                    const memberNo = (this.groupLookupMemberNo || '').trim();
                    const phone = (this.groupLookupPhone || '').trim();
                    if (! memberNo) {
                        this.groupLookupError = this.i18n.alerts.guarantor_membership;
                        return;
                    }
                    if (! phone) {
                        this.groupLookupError = this.i18n.group.lookupInvalidPhone;
                        return;
                    }
                    if (this.group.members.length >= this.groupTargetCount()) {
                        return;
                    }
                    this.groupLookupLoading = true;
                    try {
                        const res = await fetch(this.groupMemberLookupUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                member_no: memberNo,
                                phone,
                                loan_product_id: this.current?.id,
                                validate_only: true,
                            }),
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            this.groupMemberLookup = {
                                ok: false,
                                label: '',
                                error: data.message || this.i18n.group.lookupNotFound,
                                data: null,
                            };
                            this.groupLookupError = data.message || this.i18n.group.lookupNotFound;
                            return;
                        }
                        if (this.group.members.some(m => Number(m.customer_id) === Number(data.customer_id))) {
                            this.groupLookupError = this.i18n.groupMembers.duplicate;
                            this.groupMemberLookup.error = this.i18n.groupMembers.duplicate;
                            return;
                        }
                        this.groupMemberLookup = {
                            ok: true,
                            label: data.name || data.label || '',
                            error: '',
                            data,
                        };
                        this.groupLookupError = '';
                    } catch (e) {
                        this.groupLookupError = this.i18n.group.lookupNotFound;
                        this.groupMemberLookup.error = this.i18n.group.lookupNotFound;
                    } finally {
                        this.groupLookupLoading = false;
                    }
                },

                async confirmAddValidatedGroupMember() {
                    const data = this.groupMemberLookup?.data;
                    if (! data?.customer_id || ! this.groupMemberLookupUrl) return;
                    if (this.group.members.some(m => Number(m.customer_id) === Number(data.customer_id))) {
                        this.groupLookupError = this.i18n.groupMembers.duplicate;
                        return;
                    }
                    this.groupLookupLoading = true;
                    try {
                        const res = await fetch(this.groupMemberLookupUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                member_no: (this.groupLookupMemberNo || '').trim(),
                                phone: (this.groupLookupPhone || '').trim(),
                                loan_product_id: this.current?.id,
                            }),
                        });
                        const confirmed = await res.json();
                        if (! res.ok || ! confirmed.ok) {
                            this.groupLookupError = confirmed.message || this.i18n.group.lookupNotFound;
                            return;
                        }
                        this.group.members.push({
                            customer_id: confirmed.customer_id,
                            invitation_id: confirmed.invitation_id,
                            name: confirmed.name,
                            phone: confirmed.phone,
                            role: 'member',
                            requested_amount: this.group.amount_per_member,
                            status_key: confirmed.status_key || 'profile_incomplete',
                            share: confirmed.share || null,
                            avatar_url: confirmed.avatar_url || data.avatar_url || null,
                            _open: false,
                        });
                        this.groupLookupMemberNo = '';
                        this.groupLookupPhone = '';
                        this.groupProgressSummary = null;
                        this.groupMemberLookup = { ok: false, label: '', error: '', data: null };
                        this.closeAddMemberPanel();
                        this.updateGroupTotal();
                        await this.persistDraft(true);
                    } catch (e) {
                        this.groupLookupError = this.i18n.group.lookupNotFound;
                    } finally {
                        this.groupLookupLoading = false;
                    }
                },

                async lookupGroupMember() {
                    await this.validateGroupMember();
                    if (this.groupMemberLookup.ok) {
                        await this.confirmAddValidatedGroupMember();
                    }
                },

                removeGroupMember(index) {
                    const member = this.group.members[index];
                    if (! member || member.role === 'leader') return;

                    const name = member.name || 'this member';
                    const run = async () => {
                        const invitationId = Number(member.invitation_id || member.share?.invitation_id || 0);
                        if (invitationId > 0 && this.groupMemberExpireUrl) {
                            try {
                                await fetch(this.groupMemberExpireUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ invitation_id: invitationId }),
                                });
                            } catch (e) {
                                // Still remove locally if revoke fails — draft persist is source of truth for wizard.
                            }
                        }

                        this.group.members.splice(index, 1);
                        this.updateGroupTotal();
                        this.groupProgressSummary = null;
                        await this.persistDraft(true);

                        if (this.group.members.length < this.groupTargetCount()) {
                            this.openAddMemberPanel();
                            if (typeof window.showBorrowerFeedback === 'function') {
                                window.showBorrowerFeedback({
                                    title: this.i18n.group?.removeTitle || 'Member removed',
                                    message: (this.i18n.group?.addReplacementHint || 'Add another member to complete the group of :target.')
                                        .replace(':target', this.groupTargetCount()),
                                    tone: 'info',
                                });
                            }
                        }
                    };

                    if (typeof window.confirmAction === 'function') {
                        window.confirmAction({
                            title: (this.i18n.group?.removeTitle || 'Remove :name?').replace(':name', name),
                            message: this.i18n.group?.removeMessage || 'They will be removed from this application. You can invite someone else.',
                            confirmLabel: this.i18n.group?.removeConfirm || 'Remove',
                            confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                            tone: 'warning',
                            onConfirm: () => { run(); },
                        });
                        return;
                    }

                    run();
                },

                beginReservationApplication() {
                    const p = this.products.find(x => x.id == config.preselect);
                    if (! p) return;
                    this.selectProduct(p, true);
                    this.form.requested_amount = this.assetApplication.remaining_loan;
                    const maxTenure = Number(this.assetApplication.max_tenure_months) || Number(this.current?.tenure_max) || 12;
                    const minTenure = Number(this.assetApplication.min_tenure_months) || Number(this.current?.tenure_min) || 1;
                    this.form.requested_tenure_months = maxTenure;
                    this.assetTenureMin = minTenure;
                    this.assetTenureMax = maxTenure;
                    this.form.purpose = this.assetApplication.purpose || 'asset_financing';
                    // Confirm asset on the details phase first, then enter the standard wizard spine.
                    this.phase = 'details';
                    this.step = 0;
                    this.furthestStep = 0;
                    this.syncStepKey();
                    this.updateQuote();
                    this.loadReadiness(p.id);
                },

                openProduct(p) {
                    if (this.isMarketplaceProduct(p)) {
                        window.location.href = this.marketplaceUrl;
                        return;
                    }
                    this.selectProduct(p, false);
                    this.phase = 'details';
                    this.loadReadiness(p.id);
                    this.scrollWizardIntoView();
                },

                backToBrowse() {
                    window.location.href = this.loanProductsUrl || '/borrower/loan-products';
                },

                backToDetails() {
                    this.phase = 'details';
                    this.scrollWizardIntoView();
                },

                completeMissingRequirements() {
                    const url = this.readiness?.missing_action_url;
                    if (url) {
                        window.location.href = url;
                        return;
                    }
                    this.startApplication();
                },

                loadReadiness(productId) {
                    if (this._readinessPromise && this._readinessProductId === productId) {
                        return this._readinessPromise;
                    }
                    this.readinessLoading = true;
                    this._readinessProductId = productId;
                    const url = this.readinessUrl.replace('__ID__', encodeURIComponent(productId));
                    this._readinessPromise = fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    })
                        .then(res => res.ok ? res.json() : Promise.reject(res))
                        .then(data => {
                            this.readiness = data;
                            if (this.phase === 'application' && this.current && ! this.resumeLoading) {
                                this.rebuildSteps();
                                this.clampToIncompleteSetup();
                                this.enforceStepRequirements(this.isResume);
                                this.syncStepKey();
                            }
                            if (data.fees?.application !== undefined) {
                                this.applicationFee = data.fees.application;
                                this.syncFeePaidState();
                            }
                            return data;
                        })
                        .catch(() => {
                            if (this.readiness?.product?.id !== productId) {
                                this.readiness = null;
                            }
                            showWizardFeedback(this.i18n.alerts.loadProduct);
                        })
                        .finally(() => {
                            this.readinessLoading = false;
                            this._readinessPromise = null;
                            this._readinessProductId = null;
                        });
                    return this._readinessPromise;
                },

                membershipIsActive() {
                    const check = Array.isArray(this.readiness?.requirements)
                        ? this.readiness.requirements.find((r) => r.key === 'membership')
                        : null;
                    if (check && typeof check.complete === 'boolean') {
                        return check.complete;
                    }
                    return !!this.hasActiveMembership;
                },

                membershipPayUrl() {
                    const check = Array.isArray(this.readiness?.requirements)
                        ? this.readiness.requirements.find((r) => r.key === 'membership')
                        : null;
                    return check?.action_url || this.membershipRenewUrl || '/borrower/membership/renew';
                },

                async startApplication() {
                    if (! this.current) return;
                    const productId = this.current.id;
                    if (! this.readiness || this.readiness?.product?.id !== productId) {
                        await this.loadReadiness(productId);
                    }
                    if (! this.membershipIsActive()) {
                        this.showMembershipGateModal = true;
                        return;
                    }
                    if (! this.steps.length) {
                        this.selectProduct(this.current, true);
                    }
                    this.phase = 'application';
                    this.rebuildSteps();
                    if (! this.steps.length) {
                        showWizardFeedback(this.i18n.alerts.loadProduct);
                        return;
                    }
                    this.step = 0;
                    this.furthestStep = 0;
                    this.syncStepKey();
                    this.clampToIncompleteSetup();
                    await this.persistDraft(true);
                    this.scrollWizardIntoView();
                },

                /**
                 * Stale drafts (fee=0 auto-skip era) often resume on guarantor.
                 * Keep the borrower on the first incomplete setup step instead.
                 */
                clampToIncompleteSetup() {
                    if (this.supplementMode || this.isEditHop()) return;
                    const keys = (this.steps || []).map(s => s.key);
                    let forced = null;

                    if (this.isGroupProduct(this.current)) {
                        const name = (this.group?.name || '').trim();
                        const target = this.groupTargetCount();
                        const members = Array.isArray(this.group?.members) ? this.group.members : [];
                        if (! name || ! target) {
                            forced = 'group_setup';
                        } else if (members.length < target) {
                            forced = 'group_members';
                        } else {
                            const amount = Number(this.group?.amount_per_member || 0);
                            const purpose = (this.group?.purpose || this.form.purpose || '').trim();
                            const tenure = Number(this.form.requested_tenure_months || 0);
                            if (amount < this.groupAmountPerMemberMin() || ! purpose || tenure < 1) {
                                forced = 'quote';
                            }
                        }
                    } else if (this.isAssetBackedProduct(this.current)) {
                        const ids = this.selectedCustomerAssetIds?.() || [];
                        const amount = Number(this.form.requested_amount || 0);
                        const purpose = (this.form.purpose || '').trim();
                        const tenure = Number(this.form.requested_tenure_months || 0);
                        if (! ids.length || amount < (this.current?.min || 1000) || ! purpose || tenure < 1) {
                            forced = 'asset_details';
                        }
                    } else if (this.isMarketplaceProduct(this.current)) {
                        const amount = Number(this.form.requested_amount || 0);
                        const tenure = Number(this.form.requested_tenure_months || 0);
                        if (amount < 1000 || tenure < 1) {
                            forced = 'asset_tenure';
                        }
                    } else if (this.hasStep('quote')) {
                        const amount = Number(this.form.requested_amount || 0);
                        const purpose = (this.form.purpose || '').trim();
                        const tenure = Number(this.form.requested_tenure_months || 0);
                        if (amount < (this.current?.min || 1000) || ! purpose || tenure < 1) {
                            forced = 'quote';
                        }
                    }

                    if (! forced || ! keys.includes(forced)) return;
                    const forcedIndex = keys.indexOf(forced);
                    const currentIndex = Math.max(0, keys.indexOf(this.stepKey));
                    if (forcedIndex < currentIndex || ! this.stepKey || ['guarantor', 'review', 'submit', 'signature'].includes(this.stepKey)) {
                        this.step = forcedIndex;
                        this.furthestStep = Math.min(this.furthestStep || 0, forcedIndex);
                        this.syncStepKey();
                    }
                },

                withStepIcon(step) {
                    return { ...step, icon: this.stepIcons[step.key] || '' };
                },

                rebuildSteps(preserveStepKey = null) {
                    const prevKey = preserveStepKey || this.stepKey || this.steps[this.step]?.key || '';
                    // Guarantor supplement must keep the short plan (guarantor → submit)
                    // and never replace it with the full readiness plan (which includes application_fee).
                    if (this.supplementMode && this.initialPlan?.length) {
                        this.steps = this.initialPlan.map(s => this.withStepIcon(s));
                    } else if (this.readiness?.step_plan?.length) {
                        this.steps = this.readiness.step_plan.map(s => this.withStepIcon(s));
                    } else if (this.initialPlan?.length) {
                        this.steps = this.initialPlan.map(s => this.withStepIcon(s));
                    } else {
                        const stepLabels = this.i18n.steps;
                        const steps = [];
                        if (this.isGroupProduct(this.current)) {
                            steps.push({ key: 'group_setup', label: stepLabels.group_setup || this.i18n.steps.group_setup });
                            steps.push({ key: 'group_members', label: stepLabels.group_members || this.i18n.steps.group_members });
                            steps.push({ key: 'quote', label: stepLabels.quote });
                        } else if (this.isAssetBackedProduct(this.current)) {
                            steps.push({ key: 'asset_details', label: stepLabels.asset_details || this.i18n.steps.asset_details });
                        } else if (! this.isMarketplaceProduct(this.current)) {
                            steps.push({ key: 'quote', label: stepLabels.quote });
                        } else {
                            steps.push({ key: 'asset_tenure', label: stepLabels.asset_tenure || stepLabels.quote });
                        }
                        if (this.requiresGuarantor()) {
                            steps.push({ key: 'guarantor', label: this.i18n.steps.guarantor });
                        }
                        // product_questions fold into quote (same spine as Individual)
                        steps.push({ key: 'review', label: this.i18n.steps.review });
                        steps.push({ key: 'submit', label: this.i18n.steps.submit });
                        this.steps = steps.map(s => this.withStepIcon(s));
                    }

                    // Application fee / in-wizard signature / product_questions are never numbered steps —
                    // fee is a payment gate; artisan details live on Amount; signature on profile.
                    this.syncFeePaidState();
                    this.steps = this.steps.filter(s => !['application_fee', 'signature', 'product_questions'].includes(s.key));

                    this.step = this.resolveStepIndex(
                        (['application_fee', 'signature', 'product_questions'].includes(prevKey))
                            ? (prevKey === 'product_questions' ? 'quote' : (this.steps[0]?.key || ''))
                            : prevKey,
                        this.step
                    );
                    this.syncStepKey();
                },

                selectProduct(p, rebuild = true) {
                    this.current = p;
                    this.form.loan_product_id = p.id;
                    if (typeof p.application_fee === 'number') {
                        this.applicationFee = p.application_fee;
                    }
                    if (! this.form.requested_amount || this.form.requested_amount < p.min) this.form.requested_amount = p.min;
                    if (! this.form.requested_tenure_months || this.form.requested_tenure_months < p.tmin) this.form.requested_tenure_months = p.tmin;
                    // AB: borrower states soft requested amount/tenure; final offer is post-submit.
                    if (this.isAssetBackedProduct(p)) {
                        if (! this.form.customer_asset_ids?.length && this.form.customer_asset_id) {
                            this.form.customer_asset_ids = [this.form.customer_asset_id];
                        }
                    }
                    if (this.isGroupProduct(p)) {
                        this.initGroupLeader();
                        const tenureOptions = p.tenure_options || [];
                        if (tenureOptions.length) {
                            const currentTenure = Number(this.form.requested_tenure_months);
                            if (! tenureOptions.includes(currentTenure)) {
                                this.form.requested_tenure_months = tenureOptions[0];
                            }
                        }
                        if (! this.group.purpose && this.form.purpose) this.group.purpose = this.form.purpose;
                        this.refreshApplicationFeeQuote();
                    }
                    if (! this.requiresGuarantor()) this.form.guarantor_mode = 'none';
                    else if (this.form.guarantor_mode === 'none') this.form.guarantor_mode = '';
                    this.updateQuote();
                    if (rebuild) this.rebuildSteps();
                },

                estimateEmi(principal, rate, months) {
                    if (principal <= 0 || months <= 0) return 0;
                    if (rate <= 0) return Math.round(principal / months);
                    const pow = Math.pow(1 + rate, months);
                    return Math.round(principal * rate * pow / (pow - 1));
                },

                estimateWeeklyInstallment(principal, rate, months) {
                    if (principal <= 0 || months <= 0) return 0;
                    const periods = Math.max(1, months * 4);
                    const periodRate = rate / 4;
                    if (this.interestMethod() === 'flat') {
                        return Math.round((principal / periods) + (principal * periodRate));
                    }

                    return this.estimateEmi(principal, periodRate, periods);
                },

                interestMethod() {
                    const method = String(this.current?.interest_method || 'reducing').toLowerCase();

                    return method === 'flat' ? 'flat' : 'reducing';
                },

                estimateFlatMonthly(principal, rate, months) {
                    if (principal <= 0 || months <= 0) return 0;

                    return Math.round((principal / months) + (principal * rate));
                },

                repaymentCadence() {
                    const freq = (this.current?.frequency || 'weekly').toLowerCase();
                    return freq === 'monthly' ? 'monthly' : 'weekly';
                },

                canShowQuoteRewards() {
                    return this.hasActiveLoanReward() || !! this.feeLoyaltyOption?.can_redeem;
                },

                resolveMonthlyRate(product, amount) {
                    if (! product) return 0;
                    const tiers = product.tiers || [];
                    let rate = 0;
                    if (tiers.length) {
                        const tier = tiers.find(t => amount >= t.min && amount <= t.max);
                        rate = tier ? tier.rate : (product.rate || 0);
                    } else {
                        rate = product.rate || 0;
                    }
                    const engagementDiscount = Number(this.engagementBoosts?.rate_discount_fraction || 0);
                    const loyaltyDiscount = Number(this.loyaltyRateDiscount || 0);
                    return Math.max(0, rate - engagementDiscount - loyaltyDiscount);
                },

                hasActiveLoanReward() {
                    return (this.activeRewards || []).some((r) =>
                        r.benefit_type === 'rate_discount'
                        || (r.benefit_type === 'percent_discount' && r.fee_type === 'application_fee')
                    );
                },

                updateQuote() {
                    if (! this.current) return;
                    const rate = this.resolveMonthlyRate(this.current, this.form.requested_amount);
                    const months = this.form.requested_tenure_months;
                    // Group installments are quoted per member; total loan is shown separately.
                    const principal = this.isGroupProduct(this.current)
                        ? Number(this.group.amount_per_member || 0)
                        : this.form.requested_amount;
                    const cadence = this.repaymentCadence();
                    const method = this.interestMethod();
                    const monthly = method === 'flat'
                        ? this.estimateFlatMonthly(principal, rate, months)
                        : this.estimateEmi(principal, rate, months);
                    const weekly = this.estimateWeeklyInstallment(principal, rate, months);
                    const primary = cadence === 'monthly' ? monthly : weekly;
                    const periods = cadence === 'monthly' ? months : Math.max(1, months * 4);
                    const interest = Math.max(0, (primary * periods) - principal);
                    this.quote = {
                        monthly,
                        weekly,
                        primary,
                        frequency: cadence,
                        interest,
                        fees: this.applicationFee,
                        // Loan repayment only — application fee is shown on its own payment step.
                        total: primary * periods,
                    };
                    // Keep review hero in sync even before the schedule API returns.
                    // Do not use 0 as a sticky value (nullish coalescing would prefer it).
                    this.reviewSummary = {
                        ...this.reviewSummary,
                        monthly_installment: monthly,
                        installment_amount: primary > 0 ? primary : (this.reviewSummary.installment_amount || null),
                        repayment_cadence: cadence,
                    };
                    if (this.phase === 'application') {
                        this.rebuildSteps();
                    }
                },

                displayInstallmentAmount() {
                    const fromSummary = Number(this.reviewSummary?.installment_amount);
                    if (fromSummary > 0) return fromSummary;
                    const primary = Number(this.quote?.primary);
                    if (primary > 0) return primary;
                    const cadence = this.repaymentCadence();
                    if (cadence === 'weekly') {
                        const weekly = Number(this.quote?.weekly);
                        if (weekly > 0) return weekly;
                    }
                    const monthly = Number(this.quote?.monthly);
                    return monthly > 0 ? monthly : 0;
                },

                hasStep(key) {
                    return this.steps.some(s => s.key === key);
                },

                requiresGuarantor() {
                    if (this.isGroupProduct(this.current)) return false;
                    if (! this.current) return false;
                    if (this.current.requires_guarantor) return true;
                    const threshold = Number(this.current.guarantor_required_above || 0);
                    const amount = Number(this.form.requested_amount || 0);

                    return threshold > 0 && amount >= threshold;
                },

                async loadPreviousGuarantors() {
                    if (! this.previousGuarantorsUrl) return;
                    try {
                        const response = await fetch(this.previousGuarantorsUrl, { headers: { Accept: 'application/json' } });
                        const data = await response.json();
                        this.previousGuarantors = data.guarantors || [];
                    } catch (e) {
                        this.previousGuarantors = [];
                    }
                },

                async selectPreviousGuarantor(id) {
                    if (! this.selectPreviousGuarantorUrl || ! id) return;
                    this.guarantorLookup.loading = true;
                    try {
                        const response = await fetch(this.selectPreviousGuarantorUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            body: JSON.stringify({ customer_guarantor_id: id }),
                        });
                        const data = await response.json();
                        if (! data.ok) {
                            this.guarantorLookup = {
                                ...this.guarantorLookup,
                                ok: false,
                                error: data.message || this.i18n.previousGuarantor.failed,
                            };
                            return;
                        }
                        this.form.guarantor_mode = 'previous';
                        this.form.previous_guarantor_id = id;
                        this.guarantorLookup = { ok: true, loading: false, ...(data.lookup || {}) };
                        if (data.lookup?.member_no) {
                            this.form.internal_member_no = String(data.lookup.member_no).replace(/^KPF-TZ-/i, '');
                        }
                        if (data.lookup?.phone) {
                            this.form.internal_guarantor_phone = String(data.lookup.phone).replace(/^\+?255/, '');
                        }
                        if (data.lookup?.name) {
                            this.form.internal_guarantor_name = data.lookup.name;
                        }
                        // Keep the add panel open so the borrower sees the selected person,
                        // then run the same validate path used for internal members when needed.
                        this.addGuarantorOpen = true;
                        if (typeof this.validateInternalGuarantor === 'function' && this.form.internal_member_no && this.form.internal_guarantor_phone) {
                            await this.validateInternalGuarantor();
                        }
                    } finally {
                        this.guarantorLookup.loading = false;
                    }
                },

                gotoKey(key, opts = {}) {
                    const i = this.steps.findIndex(s => s.key === key);
                    if (i >= 0 && i <= (this.furthestStep ?? this.step)) {
                        if (opts.returnTo) {
                            this.returnTo = opts.returnTo;
                        }
                        this.feeGateOpen = false;
                        this.step = i;
                        this.syncStepKey();
                        if (this.stepKey === 'review') {
                            this.reviewPage = 1;
                            this.refreshReview(this.formRoot());
                        }
                        this.scrollWizardIntoView();
                    }
                },

                /** Keep sticky step/review nav in place — scroll to wizard shell, not page top. */
                scrollWizardIntoView() {
                    this.$nextTick(() => {
                        const shell = this.$root?.querySelector?.('[data-wizard-scroll-anchor]')
                            || this.$root;
                        if (! shell || typeof shell.getBoundingClientRect !== 'function') {
                            return;
                        }
                        const top = shell.getBoundingClientRect().top + window.scrollY - 12;
                        const current = window.scrollY || window.pageYOffset || 0;
                        // Only nudge when the shell is meaningfully off-screen; avoid jumping away from sticky tabs.
                        if (Math.abs(current - top) > 80) {
                            window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                        }
                    });
                },

                profileGateReturnUrl() {
                    try {
                        const url = new URL(window.location.href);
                        url.searchParams.set('resume', '1');
                        url.searchParams.set('step_key', 'submit');
                        return url.toString();
                    } catch {
                        return this.profileUrl || '/borrower/profile';
                    }
                },

                profileGateActionUrl() {
                    const base = this.firstActionUrl || this.profileUrl || null;
                    if (! base) return null;
                    try {
                        const url = new URL(base, window.location.origin);
                        url.searchParams.set('return', this.profileGateReturnUrl());
                        return url.toString();
                    } catch {
                        const sep = base.includes('?') ? '&' : '?';
                        return base + sep + 'return=' + encodeURIComponent(this.profileGateReturnUrl());
                    }
                },

                isGuarantorLocked() {
                    if (this.form.guarantor_mode === 'internal' || this.form.guarantor_mode === 'previous') {
                        return this.internalGuarantorValidated();
                    }
                    if (this.form.guarantor_mode === 'external') {
                        return !! this.externalGuarantor?.invitation_url;
                    }

                    return false;
                },

                canShowGuarantorContinue() {
                    if (! this.requiresGuarantor()) {
                        return this.isGuarantorLocked() || this.form.guarantor_mode === 'none' || ! this.addGuarantorOpen;
                    }
                    return this.isGuarantorLocked();
                },

                /** Required artisan / product-specific fields on the Amount step. */
                quoteProductQuestionsReady() {
                    const code = this.current?.code;
                    if (! code || ! this.productQuestions?.[code]?.fields?.length) {
                        return true;
                    }
                    const root = this.formRoot?.() || document.getElementById('apply-wizard');
                    if (! root || ! window.KopaFastaForm) {
                        return true;
                    }
                    const panels = root.querySelectorAll('[data-wizard-step="quote"]');
                    for (const panel of panels) {
                        if (panel.offsetParent === null && getComputedStyle(panel).display === 'none') {
                            continue;
                        }
                        const required = panel.querySelectorAll('[required]');
                        if (required.length && ! window.KopaFastaForm.isComplete(panel, { onlyVisible: true, allowEmpty: false })) {
                            return false;
                        }
                    }
                    return true;
                },

                /** Silent completeness check — used to show Continue only when the step is ready. */
                isCurrentStepReady() {
                    void this._gateTick;
                    if (this.advancing || this.resumeLoading) {
                        return false;
                    }
                    // Payment gate: Pay CTA only — no footer Continue until payment auto-advances.
                    if (this.feeGateOpen || this.stepKey === 'application_fee') {
                        return false;
                    }
                    if (this.stepKey === 'guarantor') {
                        return this.canShowGuarantorContinue();
                    }
                    if (this.stepKey === 'signature') {
                        return !! this.declarationAccepted;
                    }
                    if (this.stepKey === 'submit') {
                        return true;
                    }
                    if (this.stepKey === 'quote' && this.hasStep('quote')) {
                        if (this.isGroupProduct(this.current)) {
                            if (! this.group.amount_per_member || Number(this.group.amount_per_member) < this.groupAmountPerMemberMin()) return false;
                            if (! this.group.purpose) return false;
                            if (this.purposeNeedsDetail()) return false;
                            if (! this.form.requested_tenure_months) return false;
                            return true;
                        }
                        if (! this.form.purpose) return false;
                        if (this.purposeNeedsDetail()) return false;
                        if (! this.quoteProductQuestionsReady()) return false;
                        return true;
                    }
                    if (this.stepKey === 'group_setup' && this.hasStep('group_setup')) {
                        const count = this.groupTargetCount();
                        return !!(this.group.name || '').trim()
                            && count >= this.groupLimits.min && count <= this.groupLimits.max
                            && !!(this.group.purpose || '').trim()
                            && ! this.purposeNeedsDetail();
                    }
                    if (this.stepKey === 'group_members' && this.hasStep('group_members')) {
                        const target = this.groupTargetCount();
                        // Amount is set later on the quote spine step.
                        return this.group.members.length === target;
                    }
                    if (this.stepKey === 'asset_details' && this.hasStep('asset_details')) {
                        if (! this.customerAssets?.length || ! this.selectedCustomerAssetIds()?.length) return false;
                        const missingInsurance = this.selectedCustomerAssetIds().some((id) => {
                            const asset = (this.customerAssets || []).find(a => String(a.id) === String(id));
                            return asset && asset.asset_type === 'vehicle' && ! asset.has_insurance;
                        });
                        if (missingInsurance) return false;
                        if (! this.form.requested_amount || this.form.requested_amount < (this.current?.min || 1000)) return false;
                        if (this.current && this.form.requested_amount > this.current.max) return false;
                        if (! this.form.requested_tenure_months || this.form.requested_tenure_months < (this.current?.tmin || 1)) return false;
                        if (! this.form.purpose) return false;
                        if (this.purposeNeedsDetail()) return false;
                        return true;
                    }
                    // Generic DOM check for other wizard panels (product questions, etc.)
                    const panel = document.querySelector(`[data-wizard-step="${this.stepKey}"]`)
                        || document.querySelector(`[data-step-key="${this.stepKey}"]`)
                        || document.getElementById('apply-wizard');
                    if (panel && window.KopaFastaForm) {
                        const required = panel.querySelectorAll('[required]');
                        if (required.length) {
                            return window.KopaFastaForm.isComplete(panel, { onlyVisible: true, allowEmpty: false });
                        }
                    }
                    return true;
                },

                guarantorSummaryText() {
                    if (this.form.guarantor_mode === 'internal') {
                        return this.guarantorLookup.label || this.form.internal_guarantor_name || '—';
                    }
                    if (this.form.guarantor_mode === 'external') {
                        return [this.form.external_first_name, this.form.external_last_name].filter(Boolean).join(' ') || '—';
                    }

                    return '—';
                },

                async changeGuarantor() {
                    if (this.guarantorChanging || ! this.form.loan_product_id) {
                        return;
                    }
                    this.guarantorChanging = true;
                    try {
                        const res = await fetch(this.guarantorExpireUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ loan_product_id: this.form.loan_product_id }),
                        });
                        if (! res.ok) {
                            throw new Error('expire failed');
                        }
                        this.guarantorLookup = { ok: false, label: '', error: '', memberKey: '', phone: '', name: '' };
                        this.externalGuarantor = null;
                        this.internalGuarantor = null;
                        this.guarantorErrors = {};
                        this.form.internal_member_no = '';
                        this.form.internal_guarantor_phone = '';
                        this.form.internal_guarantor_name = '';
                        this.form.external_first_name = '';
                        this.form.external_middle_name = '';
                        this.form.external_last_name = '';
                        this.form.external_phone = '';
                        this.form.external_email = '';
                        this.form.external_relationship = '';
                        this.form.external_region = '';
                        this.form.external_district = '';
                        this.form.guarantor_mode = this.requiresGuarantor() ? '' : 'none';
                        this.addGuarantorOpen = true;
                        this.scheduleDraftSave();
                    } catch {
                        this.guarantorLookup = {
                            ...this.guarantorLookup,
                            ok: false,
                            error: this.i18n.alerts.guarantor_lookup_failed,
                        };
                    } finally {
                        this.guarantorChanging = false;
                    }
                },

                guarantorStatusLabel() {
                    if (this.form.guarantor_mode === 'internal' || this.form.guarantor_mode === 'previous') {
                        if (this.internalGuarantor?.borrower_status_label) {
                            return this.internalGuarantor.borrower_status_label;
                        }
                        const code = this.internalGuarantor?.borrower_status_code;
                        if (code && this.i18n.alerts.guarantorStatus?.[code]) {
                            return this.i18n.alerts.guarantorStatus[code];
                        }
                        if (this.internalGuarantor?.invitation_id) {
                            return this.i18n.alerts.guarantorStatus?.pending_acceptance
                                || this.i18n.alerts.guarantorStatus.invitation_sent
                                || this.i18n.alerts.guarantorStatus.internal_validated;
                        }
                        return this.i18n.alerts.guarantorStatus?.pending_acceptance
                            || this.i18n.alerts.guarantorStatus.internal_validated;
                    }
                    if (this.form.guarantor_mode === 'external') {
                        if (this.externalGuarantor?.borrower_status_label) {
                            return this.externalGuarantor.borrower_status_label;
                        }
                        const code = this.externalGuarantor?.borrower_status_code || 'invitation_sent';
                        return this.i18n.alerts.guarantorStatus?.[code]
                            || this.i18n.alerts.guarantorStatus.invitation_sent;
                    }

                    return '—';
                },

                guarantorStatusCode() {
                    if (this.form.guarantor_mode === 'external') {
                        return this.externalGuarantor?.borrower_status_code
                            || (this.externalGuarantor?.status === 'accepted' ? 'registration_in_progress' : 'invitation_sent');
                    }
                    if (this.internalGuarantor?.borrower_status_code) {
                        return this.internalGuarantor.borrower_status_code;
                    }

                    return this.internalGuarantor?.invitation_id ? 'pending_acceptance' : 'pending_acceptance';
                },

                guarantorProgressSteps() {
                    const steps = this.form.guarantor_mode === 'external'
                        ? (this.externalGuarantor?.steps || [])
                        : (this.internalGuarantor?.steps || []);
                    return Array.isArray(steps) ? steps : [];
                },

                guarantorHoldTitle() {
                    const code = this.guarantorStatusCode();
                    if (code === 'ready') {
                        return this.i18n.submitStep?.guarantor_ready_title
                            || this.i18n.alerts.guarantorStatus?.ready
                            || 'Guarantor ready';
                    }
                    if (code === 'pending_profile') {
                        return this.i18n.submitStep?.guarantor_profile_title
                            || this.i18n.alerts.guarantorStatus?.pending_profile
                            || 'Guarantor finishing profile';
                    }
                    return this.i18n.submitStep?.guarantor_hold_title || 'Guarantor pending';
                },

                guarantorHoldHint() {
                    const code = this.guarantorStatusCode();
                    const percent = this.form.guarantor_mode === 'external'
                        ? this.externalGuarantor?.profile_percent
                        : this.internalGuarantor?.profile_percent;
                    if (code === 'ready') {
                        return this.i18n.submitStep?.guarantor_ready_hint || '';
                    }
                    if (code === 'pending_profile') {
                        const tpl = this.i18n.submitStep?.guarantor_profile_hint || '';
                        return tpl.replace(':percent', String(percent ?? 0));
                    }
                    return this.i18n.submitStep?.guarantor_hold_hint || '';
                },

                guarantorLockedSummaryText() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected') {
                        return this.i18n.guarantorLocked?.declined || 'Declined';
                    }

                    return this.i18n.guarantorLocked?.summary || '';
                },

                guarantorLockedCardClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'bg-rose-50 ring-rose-200';
                    }
                    if (code === 'ready' || code === 'accepted') {
                        return 'bg-emerald-50 ring-emerald-200';
                    }
                    if (code === 'pending_profile' || code === 'guarantee_pending') {
                        return 'bg-amber-50 ring-amber-200';
                    }

                    return 'bg-amber-50 ring-amber-200';
                },

                guarantorLockedCardTextClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'text-rose-900';
                    }
                    if (code === 'ready' || code === 'accepted') {
                        return 'text-emerald-900';
                    }

                    return 'text-amber-900';
                },

                guarantorLockedCardMutedClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'text-rose-700';
                    }
                    if (code === 'ready' || code === 'accepted') {
                        return 'text-emerald-700';
                    }

                    return 'text-brand';
                },

                guarantorLockedCardBodyClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'text-rose-800';
                    }
                    if (code === 'ready' || code === 'accepted') {
                        return 'text-emerald-800';
                    }

                    return 'text-amber-800';
                },

                guarantorStatusBadgeClass() {
                    const code = this.guarantorStatusCode();

                    if (code === 'ready' || code === 'accepted') {
                        return 'bg-emerald-100 text-emerald-900 ring-emerald-200';
                    }
                    if (code === 'rejected' || code === 'expired') {
                        return 'bg-rose-100 text-rose-900 ring-rose-200';
                    }
                    if (code === 'pending_profile' || code === 'guarantee_pending' || code === 'kyc_in_progress') {
                        return 'bg-amber-100 text-amber-900 ring-amber-200';
                    }
                    if (code === 'pending_acceptance' || code === 'invitation_sent') {
                        return 'bg-sky-100 text-sky-900 ring-sky-200';
                    }

                    return 'bg-sky-100 text-sky-900 ring-sky-200';
                },

                guarantorReviewStatus() {
                    return this.guarantorStatusLabel();
                },

                async refreshGuarantorStatus() {
                    const invitationId = this.form.guarantor_mode === 'external'
                        ? this.externalGuarantor?.invitation_id
                        : this.internalGuarantor?.invitation_id;
                    if (! this.guarantorStatusUrl || ! invitationId) {
                        return;
                    }
                    try {
                        const params = new URLSearchParams({
                            invitation_id: String(invitationId),
                        });
                        const res = await fetch(`${this.guarantorStatusUrl}?${params}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.ok || ! data.share) {
                            return;
                        }
                        if (this.form.guarantor_mode === 'external') {
                            this.externalGuarantor = {
                                ...this.externalGuarantor,
                                ...data.share,
                            };
                        } else {
                            this.internalGuarantor = {
                                ...(this.internalGuarantor || {}),
                                ...data.share,
                            };
                        }
                        this.review.guarantorStatus = this.guarantorReviewStatus();
                        this.scheduleDraftSave();
                    } catch {
                        // Non-blocking refresh.
                    }
                },

                async refreshExternalGuarantorStatus() {
                    await this.refreshGuarantorStatus();
                },

                async loadRepaymentSchedule() {
                    if (! this.repaymentPreviewUrl || ! this.form.loan_product_id) {
                        return;
                    }
                    this.scheduleLoading = true;
                    try {
                        const previewAmount = this.isGroupProduct(this.current)
                            ? (this.group.amount_per_member || this.form.requested_amount)
                            : this.form.requested_amount;
                        const params = new URLSearchParams({
                            loan_product_id: String(this.form.loan_product_id),
                            requested_amount: String(previewAmount),
                            requested_tenure_months: String(this.form.requested_tenure_months),
                        });
                        const res = await fetch(`${this.repaymentPreviewUrl}?${params}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.ok) {
                            this.repaymentSchedule = data.schedule || [];
                            this.scheduleDatesAvailable = !!data.dates_available;
                            if (data.engagement) {
                                if (data.engagement.limit_amount) {
                                    this.qualificationLimit = Number(data.engagement.limit_amount);
                                }
                                if (data.engagement.processing_sla) {
                                    this.processingSla = data.engagement.processing_sla;
                                }
                            }
                            const apiInstallment = Number(data.summary?.installment_amount);
                            const fallbackInstallment = this.displayInstallmentAmount();
                            this.reviewSummary = {
                                monthly_rate_pct: data.summary?.monthly_rate_pct ?? 0,
                                application_fee: data.summary?.application_fee ?? this.applicationFee,
                                monthly_installment: data.summary?.monthly_installment ?? this.quote.monthly,
                                installment_amount: apiInstallment > 0 ? apiInstallment : (fallbackInstallment || null),
                                repayment_cadence: data.summary?.repayment_cadence ?? this.quote.frequency ?? this.repaymentCadence(),
                            };
                        }
                    } catch {
                        this.repaymentSchedule = [];
                    } finally {
                        this.scheduleLoading = false;
                    }
                },

                refreshReview(formEl) {
                    this.updateQuote();
                    const form = formEl instanceof HTMLFormElement ? formEl : this.formRoot();
                    const snapshot = this.borrowerSnapshot || {};
                    if (! form) {
                        this.review.personal = snapshot.personal || [this.form.first_name, this.form.last_name].filter(Boolean).join(' · ');
                        this.review.employment = snapshot.employment || '';
                        this.review.residence = snapshot.residence || '';
                    } else {
                        const fd = new FormData(form);
                        const g = (n) => fd.get(n) || '';
                        this.review.personal = snapshot.personal || [g('first_name'), g('last_name'), g('national_id')].filter(Boolean).join(' · ');
                        const activity = this.activityTypeLabels[g('activity_type')] || g('activity_type');
                        const income = this.incomeRangeLabels[g('income_range')] || g('income_range');
                        this.review.employment = snapshot.employment || [activity, income].filter(Boolean).join(' · ');
                        this.review.residence = snapshot.residence || [g('street'), g('ward'), g('district'), g('region')].filter(Boolean).join(', ');
                        const nokRel = g('nok_relationship');
                        const nokRelLabel = this.kinRelationshipLabels[nokRel] || nokRel;
                        this.review.nok = [g('nok_name'), nokRelLabel, g('nok_phone')].filter(Boolean).join(' · ');
                        this.review.activity = [activity, income].filter(Boolean).join(' · ');
                    }

                    if (this.form.guarantor_mode === 'internal') {
                        this.review.guarantorType = '';
                        this.review.guarantorName = this.guarantorLookup.label || this.form.internal_guarantor_name || this.form.internal_member_no || '—';
                    } else if (this.form.guarantor_mode === 'external') {
                        this.review.guarantorType = '';
                        this.review.guarantorName = [this.form.external_first_name, this.form.external_last_name].filter(Boolean).join(' ') || '—';
                    } else {
                        this.review.guarantorType = '—';
                        this.review.guarantorName = '—';
                    }
                    this.review.guarantorStatus = this.guarantorReviewStatus();
                    this.review.guarantor = this.review.guarantorName;
                    // Load schedule as soon as review opens so page-1 installment is accurate.
                    this.loadRepaymentSchedule();
                },

                setReviewPage(page) {
                    const next = Math.min(this.reviewPageCount, Math.max(1, Number(page) || 1));
                    this.reviewPage = next;
                    if (next >= 2) {
                        this.loadRepaymentSchedule();
                    }
                    // Keep the sticky review rail in place — do not scroll the wizard shell up.
                },

                reviewContinue() {
                    if (this.stepKey === 'review' && this.reviewPage < this.reviewPageCount) {
                        this.setReviewPage(this.reviewPage + 1);
                        return true;
                    }
                    return false;
                },

                reviewBack() {
                    if (this.stepKey === 'review' && this.reviewPage > 1) {
                        this.setReviewPage(this.reviewPage - 1);
                        return true;
                    }
                    return false;
                },

                formRoot() {
                    const ref = this.$refs?.wizardForm;
                    if (ref instanceof HTMLFormElement) return ref;
                    const byId = document.getElementById('apply-wizard-form');
                    if (byId instanceof HTMLFormElement) return byId;
                    const scoped = this.$el?.querySelector?.('form[data-apply-wizard-form]');
                    if (scoped instanceof HTMLFormElement) return scoped;
                    const nested = this.$el?.querySelector?.('form');
                    if (nested instanceof HTMLFormElement) return nested;
                    return null;
                },

                onExternalRegionChange() {
                    this.form.external_district = '';
                },

                readFormField(name) {
                    const root = this.formRoot();
                    if (! root) {
                        if (Object.prototype.hasOwnProperty.call(this.form, name)) {
                            const fromModel = this.form[name];
                            if (fromModel !== undefined && fromModel !== null) {
                                return String(fromModel).trim();
                            }
                        }
                        return '';
                    }
                    const nodes = Array.from(root.querySelectorAll(`[name="${name}"]`));
                    const radios = nodes.filter((el) => el.type === 'radio');
                    if (radios.length) {
                        const checked = radios.find((el) => el.checked);
                        if (checked) {
                            return String(checked.value || '').trim();
                        }
                    }
                    const el = nodes.find((node) => node.type !== 'radio' && String(node.value || '').trim() !== '');
                    if (el) {
                        return String(el.value).trim();
                    }
                    if (Object.prototype.hasOwnProperty.call(this.form, name)) {
                        const fromModel = this.form[name];
                        if (fromModel !== undefined && fromModel !== null) {
                            return String(fromModel).trim();
                        }
                    }
                    return '';
                },

                syncGuarantorFormFromDom() {
                    const fields = [
                        'internal_member_no', 'internal_guarantor_phone', 'internal_guarantor_name',
                        'external_first_name', 'external_middle_name', 'external_last_name',
                        'external_relationship', 'external_phone', 'external_email',
                        'external_region', 'external_district',
                    ];
                    fields.forEach((name) => {
                        const value = this.readFormField(name);
                        if (value !== '' && Object.prototype.hasOwnProperty.call(this.form, name)) {
                            this.form[name] = value;
                        }
                    });
                    // Mode is controlled by Alpine buttons — never overwrite from a hidden submit field.
                },

                externalGuarantorPayload() {
                    return {
                        loan_product_id: this.form.loan_product_id,
                        external_first_name: this.readFormField('external_first_name') || this.form.external_first_name,
                        external_middle_name: this.readFormField('external_middle_name') || this.form.external_middle_name,
                        external_last_name: this.readFormField('external_last_name') || this.form.external_last_name,
                        external_phone: this.readFormField('external_phone') || this.form.external_phone,
                        external_email: this.readFormField('external_email') || this.form.external_email,
                        external_relationship: this.readFormField('external_relationship') || this.form.external_relationship,
                        external_region: this.readFormField('external_region') || this.form.external_region,
                        external_district: this.readFormField('external_district') || this.form.external_district,
                        external_invitation_id: this.externalGuarantor?.invitation_id || null,
                    };
                },

                externalGuarantorFingerprint() {
                    const p = this.externalGuarantorPayload();
                    return JSON.stringify({
                        external_first_name: (p.external_first_name || '').toString().trim(),
                        external_last_name: (p.external_last_name || '').toString().trim(),
                        external_relationship: (p.external_relationship || '').toString().trim(),
                        external_phone: (p.external_phone || '').toString().trim(),
                        external_region: (p.external_region || '').toString().trim(),
                        external_district: (p.external_district || '').toString().trim(),
                    });
                },

                invalidateExternalInvite() {
                    if (! this.externalGuarantor?.invitation_url) {
                        return;
                    }
                    const current = this.externalGuarantorFingerprint();
                    if (this.externalGuarantor._fingerprint && this.externalGuarantor._fingerprint !== current) {
                        this.externalGuarantor = null;
                    }
                },

                externalGuarantorMissingFields() {
                    const required = {
                        external_first_name: this.i18n.guarantorFields.labels.external_first_name,
                        external_last_name: this.i18n.guarantorFields.labels.external_last_name,
                        external_relationship: this.i18n.guarantorFields.labels.external_relationship,
                        external_phone: this.i18n.guarantorFields.labels.external_phone,
                        external_region: this.i18n.guarantorFields.labels.external_region,
                        external_district: this.i18n.guarantorFields.labels.external_district,
                    };
                    const p = this.externalGuarantorPayload();
                    const missing = {};
                    Object.entries(required).forEach(([key, label]) => {
                        if (! (p[key] || '').toString().trim()) {
                            missing[key] = label + ' ' + this.i18n.guarantorFields.isRequired;
                        }
                    });
                    return missing;
                },

                setGuarantorFieldErrors(missingMap) {
                    this.guarantorErrors = { ...missingMap };
                    const lines = Object.values(missingMap || {});
                    if (lines.length) {
                        showWizardFeedback({
                            title: this.i18n.guarantorFields?.missingFieldsTitle || 'Please complete the following:',
                            lines,
                            tone: 'error',
                        });
                    }
                },

                isExternalGuarantorComplete() {
                    return Object.keys(this.externalGuarantorMissingFields()).length === 0;
                },

                scheduleExternalInvitePrep() {
                    clearTimeout(this.externalInviteTimer);
                    this.externalInviteTimer = setTimeout(() => {
                        if (this.form.guarantor_mode !== 'external') {
                            return;
                        }
                        this.invalidateExternalInvite();
                    }, 600);
                },

                internalGuarantorFieldsFilled() {
                    this.syncGuarantorFormFromDom();
                    return !! (
                        this.readFormField('internal_member_no')
                        && this.readFormField('internal_guarantor_phone')
                        && this.readFormField('internal_guarantor_name')
                    );
                },

                async signApplication() {
                    if (this.advancing) {
                        return;
                    }
                    if (! this.declarationAccepted) {
                        showWizardFeedback(this.i18n.alerts.acceptTerms);
                        return;
                    }
                    const form = this.formRoot();
                    const sigData = this.readSignatureFromPad(form);
                    if (! sigData) {
                        showWizardFeedback(this.i18n.alerts.drawSignature);
                        return;
                    }
                    this.advancing = true;
                    try {
                        this.borrowerSignature = {
                            signer_name: this.verifiedLegalName,
                            signature_data: sigData,
                            consent_accepted: true,
                            signed_at: new Date().toISOString(),
                        };
                        this.declarationAccepted = true;
                        await this.persistDraft(true);
                        const submitIndex = this.steps.findIndex(s => s.key === 'submit');
                        if (submitIndex >= 0) {
                            this.step = submitIndex;
                        } else if (this.step < this.steps.length - 1) {
                            this.step++;
                        }
                        this.syncStepKey();
                        await this.persistDraft(true);
                        this.$nextTick(() => this.syncSubmitPayload(form));
                        this.scrollWizardIntoView();
                    } finally {
                        this.advancing = false;
                    }
                },

                async generateExternalInvite() {
                    this.syncGuarantorFormFromDom();
                    const missing = this.externalGuarantorMissingFields();
                    if (Object.keys(missing).length) {
                        this.setGuarantorFieldErrors(missing);
                        this.scrollWizardIntoView();
                        return;
                    }
                    this.guarantorErrors = {};
                    this.guarantorInviteError = '';
                    await this.prepareExternalGuarantorInvite();
                },

                async prepareExternalGuarantorInvite() {
                    this.guarantorInviteError = '';
                    if (! this.guarantorInviteUrl) {
                        this.guarantorInviteError = this.i18n.alerts.guarantor_invite_failed;
                        return false;
                    }
                    if (! this.form.loan_product_id) {
                        this.guarantorInviteError = this.i18n.alerts.loadProduct;
                        return false;
                    }
                    this.guarantorInvitePreparing = true;
                    try {
                        const res = await fetch(this.guarantorInviteUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(this.externalGuarantorPayload()),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.ok || ! data.share) {
                            this.guarantorInviteError = data.message || this.i18n.alerts.guarantor_invite_failed;
                            return false;
                        }
                        this.externalGuarantor = {
                            ...data.share,
                            _fingerprint: this.externalGuarantorFingerprint(),
                        };
                        this.guarantorInviteError = '';
                        this.addGuarantorOpen = false;
                        this.scheduleDraftSave();
                        return true;
                    } catch {
                        this.guarantorInviteError = this.i18n.alerts.guarantor_invite_failed;
                        return false;
                    } finally {
                        this.guarantorInvitePreparing = false;
                    }
                },

                async validateStep() {
                    if (this.stepKey === 'quote' && this.hasStep('quote')) {
                        this.syncQuoteFormFromDom();
                        if (this.isGroupProduct(this.current)) {
                            if (! this.group.amount_per_member || Number(this.group.amount_per_member) < this.groupAmountPerMemberMin()) {
                                showWizardFeedback(this.i18n.group.amountRequired);
                                return false;
                            }
                            if (! this.group.purpose) {
                                showWizardFeedback(this.i18n.group.purposeRequired);
                                return false;
                            }
                            if (this.purposeNeedsDetail()) {
                                this.purposeEditing = true;
                                showWizardFeedback(this.i18n.alerts?.purposeOtherRequired || this.i18n.apply?.quote?.purpose_other_required);
                                return false;
                            }
                            if (! this.form.requested_tenure_months) {
                                showWizardFeedback(this.i18n.assetDetails?.tenureRequired || 'Select a repayment period.');
                                return false;
                            }
                            this.syncGroupAmounts();
                            this.form.purpose = this.normalizePurposeKey(this.group.purpose);
                        } else {
                            if (! this.form.purpose) {
                                showWizardFeedback(this.i18n.alerts.selectPurpose);
                                return false;
                            }
                            if (this.purposeNeedsDetail()) {
                                this.purposeEditing = true;
                                showWizardFeedback(this.i18n.alerts?.purposeOtherRequired || this.i18n.apply?.quote?.purpose_other_required);
                                return false;
                            }
                            if (! this.quoteProductQuestionsReady()) {
                                showWizardFeedback(this.i18n.productQuestions?.complete || this.i18n.alerts?.productQuestionsRequired || 'Complete the loan details on this step before continuing.');
                                return false;
                            }
                        }
                    }
                    if (this.stepKey === 'group_setup' && this.hasStep('group_setup')) {
                        if (! (this.group.name || '').trim()) {
                            showWizardFeedback(this.i18n.group.nameRequiredStep);
                            return false;
                        }
                        const count = this.groupTargetCount();
                        if (! count || count < this.groupLimits.min || count > this.groupLimits.max) {
                            showWizardFeedback(this.i18n.group.memberCountRange);
                            return false;
                        }
                        if (! this.group.purpose) {
                            showWizardFeedback(this.i18n.group.purposeRequired);
                            return false;
                        }
                        if (this.purposeNeedsDetail()) {
                            this.purposeEditing = true;
                            showWizardFeedback(this.i18n.alerts?.purposeOtherRequired || this.i18n.apply?.quote?.purpose_other_required);
                            return false;
                        }
                        this.form.purpose = this.normalizePurposeKey(this.group.purpose);
                        if (! this.group.amount_per_member) {
                            this.group.amount_per_member = this.groupAmountPerMemberMin();
                        }
                    }
                    if (this.stepKey === 'group_members' && this.hasStep('group_members')) {
                        await this.refreshGroupMemberStatuses();
                        const target = this.groupTargetCount();
                        if (this.group.members.length !== target) {
                            showWizardFeedback(this.i18n.group.membersRequired);
                            return false;
                        }
                        if (! this.group.amount_per_member || Number(this.group.amount_per_member) < this.groupAmountPerMemberMin()) {
                            this.group.amount_per_member = this.groupAmountPerMemberMin();
                        }
                        this.syncGroupAmounts();
                    }
                    if (this.stepKey === 'asset_details' && this.hasStep('asset_details')) {
                        if (! this.customerAssets.length) {
                            showWizardFeedback(this.i18n.assetDetails.noAssetsTitle);
                            return false;
                        }
                        if (! this.selectedCustomerAssetIds().length) {
                            showWizardFeedback(this.i18n.assetDetails.assetRequired);
                            return false;
                        }
                        const missingInsuranceId = this.selectedCustomerAssetIds().find((id) => {
                            const asset = (this.customerAssets || []).find(a => String(a.id) === String(id));
                            return asset && asset.asset_type === 'vehicle' && ! asset.has_insurance;
                        });
                        if (missingInsuranceId) {
                            const base = this.profileAssetsUrl || this.profileUrl || '/borrower/profile';
                            window.location = base + (base.includes('?') ? '&' : '?') + 'edit=' + encodeURIComponent(missingInsuranceId) + '&focus=insurance';
                            return false;
                        }
                        if (! this.form.requested_amount || this.form.requested_amount < (this.current?.min || 1000)) {
                            showWizardFeedback(this.i18n.assetDetails.amountRequired || this.i18n.alerts.amountRequired);
                            return false;
                        }
                        if (this.current && this.form.requested_amount > this.current.max) {
                            showWizardFeedback(`Amount must be at most ${this.formatTzs(this.current.max)}.`);
                            return false;
                        }
                        if (! this.form.requested_tenure_months || this.form.requested_tenure_months < (this.current?.tmin || 1)) {
                            showWizardFeedback(this.i18n.assetDetails.tenureRequired || this.i18n.alerts.tenureRequired);
                            return false;
                        }
                        if (! this.form.purpose) {
                            showWizardFeedback(this.i18n.alerts.selectPurpose || this.i18n.assetDetails.purposeRequired);
                            return false;
                        }
                        if (this.purposeNeedsDetail()) {
                            this.purposeEditing = true;
                            showWizardFeedback(this.i18n.alerts?.purposeOtherRequired || this.i18n.apply?.quote?.purpose_other_required);
                            return false;
                        }
                    }
                    if (this.stepKey === 'guarantor' && this.hasStep('guarantor')) {
                        this.syncGuarantorFormFromDom();
                        if (! this.requiresGuarantor() && (! this.form.guarantor_mode || this.form.guarantor_mode === 'none')) {
                            return await new Promise((resolve) => {
                                if (typeof window.confirmForm !== 'function') {
                                    this.form.guarantor_mode = 'none';
                                    resolve(true);
                                    return;
                                }
                                window.confirmForm(null, {
                                    title: this.i18n.alerts?.guarantor_skip_title || 'Continue without a guarantor?',
                                    message: this.i18n.alerts?.guarantor_skip_message || this.i18n.apply?.guarantor_optional_hint || '',
                                    confirmLabel: this.i18n.continue || 'Continue',
                                    confirmClass: 'bg-brand-gold hover:bg-yellow-400 text-brand',
                                    onConfirm: () => {
                                        this.form.guarantor_mode = 'none';
                                        resolve(true);
                                    },
                                    onCancel: () => resolve(false),
                                });
                            });
                        }
                        if (! this.form.guarantor_mode || this.form.guarantor_mode === 'none') {
                            showWizardFeedback({
                                tone: 'warning',
                                title: this.i18n.alerts?.guarantor_required_title || 'Guarantor required',
                                message: this.i18n.alerts.selectGuarantor,
                            });
                            return false;
                        }
                        if (this.form.guarantor_mode === 'internal' || this.form.guarantor_mode === 'previous') {
                            if (! this.internalGuarantorValidated()) {
                                showWizardFeedback({
                                    tone: 'warning',
                                    title: this.i18n.alerts?.guarantor_required_title || 'Guarantor required',
                                    message: this.i18n.alerts.guarantor_validate_first,
                                });
                                this.scrollWizardIntoView();
                                return false;
                            }
                            return true;
                        }
                        if (this.form.guarantor_mode === 'external') {
                            if (! this.externalGuarantor?.invitation_url) {
                                const missing = this.externalGuarantorMissingFields();
                                if (Object.keys(missing).length) {
                                    this.setGuarantorFieldErrors(missing);
                                    showWizardFeedback({
                                        tone: 'warning',
                                        title: this.i18n.alerts?.guarantor_fields_title || 'Complete guarantor details',
                                        message: this.i18n.alerts?.guarantor_fields_message || this.i18n.apply?.guarantor_fields?.complete_fields_first,
                                    });
                                    this.scrollWizardIntoView();
                                    return false;
                                }
                                showWizardFeedback({
                                    tone: 'warning',
                                    title: this.i18n.alerts?.guarantor_invite_title || 'Generate invitation link',
                                    message: this.i18n.alerts.guarantor_external_invite_required
                                        || this.i18n.alerts.guarantor_validate_first,
                                });
                                this.scrollWizardIntoView();
                                return false;
                            }
                            this.guarantorErrors = {};
                            this.guarantorInviteError = '';
                            return true;
                        }
                    }
                    if (this.stepKey === 'application_fee') {
                        if (this.supplementMode) {
                            return true;
                        }
                        await this.refreshApplicationFeeQuote();
                        if (this.effectiveFeeAmount() > 0) {
                            const st = this.applicationFeeState?.status || '';
                            if (! ['paid', 'waived', 'pending'].includes(st)) {
                                showWizardFeedback({
                                    tone: 'warning',
                                    title: this.i18n.applicationFee?.pay_title || 'Payment required',
                                    message: this.i18n.applicationFee.requiredBeforeContinue,
                                });
                                return false;
                            }
                        } else if (! this.applicationFeePaid) {
                            await this.autoWaiveApplicationFeeIfNeeded();
                        }
                    }
                    if (this.feeGateOpen) {
                        await this.refreshApplicationFeeQuote();
                        if (! this.feeGateSatisfied()) {
                            showWizardFeedback({
                                tone: 'warning',
                                title: this.i18n.applicationFee?.pay_title || 'Payment required',
                                message: this.i18n.applicationFee.requiredBeforeContinue,
                            });
                            return false;
                        }
                        this.feeGateOpen = false;
                        return true;
                    }
                    return true;
                },

                internalGuarantorValidated() {
                    this.syncGuarantorFormFromDom();
                    const member = this.readFormField('internal_member_no');
                    const phone = this.readFormField('internal_guarantor_phone');

                    return this.guarantorLookup.ok
                        && this.guarantorLookup.memberKey === member
                        && this.guarantorLookup.phone === phone;
                },

                async validateInternalGuarantor() {
                    this.syncGuarantorFormFromDom();
                    const member = this.readFormField('internal_member_no');
                    const phone = this.readFormField('internal_guarantor_phone');
                    const errors = {};
                    if (! member) {
                        errors.internal_member_no = this.i18n.alerts.guarantor_membership;
                    }
                    if (! phone) {
                        errors.internal_guarantor_phone = this.i18n.alerts.guarantor_phone;
                    }
                    if (Object.keys(errors).length) {
                        this.setGuarantorFieldErrors(errors);
                        this.scrollWizardIntoView();
                        return;
                    }
                    this.guarantorErrors = {};
                    this.guarantorValidating = true;
                    this.guarantorLookup = { ok: false, label: '', error: '', memberKey: member, phone, name: '' };
                    try {
                        const res = await fetch(this.guarantorLookupUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                membership_no: member,
                                phone,
                                loan_product_id: this.form.loan_product_id || null,
                            }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.ok) {
                            const message = data.message || this.i18n.alerts.guarantor_lookup_failed;
                            this.guarantorLookup.error = message;
                            showWizardFeedback({
                                tone: 'error',
                                title: this.i18n.guarantorFields?.validationFailedTitle
                                    || 'Could not validate guarantor',
                                message,
                            });
                            return;
                        }
                        const resolvedName = data.name || data.label || '';
                        this.form.internal_guarantor_name = resolvedName;
                        this.guarantorLookup = {
                            ok: true,
                            label: data.label || resolvedName,
                            error: '',
                            memberKey: member,
                            phone,
                            name: resolvedName,
                        };
                        if (data.invite) {
                            this.internalGuarantor = data.invite;
                            this.externalGuarantor = null;
                        }
                        this.addGuarantorOpen = false;
                        showWizardFeedback({
                            tone: 'success',
                            title: this.i18n.guarantorFields?.validatedTitle || 'Guarantor verified',
                            message: data.message
                                || this.i18n.alerts?.guarantor_notified_in_app?.replace(':name', resolvedName)
                                || this.i18n.alerts?.guarantor_verified
                                || 'Guarantor verified successfully.',
                        });
                        this.scheduleDraftSave();
                    } catch {
                        const message = this.i18n.alerts.guarantor_lookup_failed;
                        this.guarantorLookup.error = message;
                        showWizardFeedback({
                            tone: 'error',
                            title: this.i18n.guarantorFields?.validationFailedTitle
                                || 'Could not validate guarantor',
                            message,
                        });
                    } finally {
                        this.guarantorValidating = false;
                    }
                },

                async next() {
                    if (this.advancing || this.resumeLoading) return;
                    if (this.guarantorInvitePreparing && this.stepKey === 'guarantor') return;
                    if (this.reviewContinue()) return;
                    if (! this.steps.length) {
                        this.rebuildSteps();
                    }
                    if (! this.steps.length) {
                        showWizardFeedback(this.i18n.alerts.loadProduct);
                        return;
                    }
                    this.advancing = true;
                    try {
                        this.syncQuoteFormFromDom();
                        if (! await this.validateStep()) return;

                        await this.persistDraft(true);
                        if (this.step >= this.steps.length - 1) {
                            return;
                        }
                        // Edit amount / edit guarantor: finish and leave the full process.
                        const returnKey = this.returnTo;
                        const fromEdit = ['quote', 'asset_details', 'asset_tenure', 'group_setup', 'guarantor'].includes(this.stepKey);
                        if (returnKey === 'profile' && fromEdit) {
                            this.returnTo = null;
                            if (this.profileUrl) {
                                window.location.href = this.profileUrl;
                                return;
                            }
                        }
                        if (returnKey && fromEdit && this.steps.some(s => s.key === returnKey)) {
                            this.gotoKey(returnKey);
                            this.returnTo = null;
                            return;
                        }
                        const nextKey = this.steps[this.step + 1]?.key;
                        // Refresh fee quote before leaving setup so group (and IL) always hit
                        // the shared payments.show gate when a fee is due.
                        if (! this.supplementMode && ! this.isEditHop() && nextKey
                            && ['guarantor', 'product_questions', 'review', 'signature', 'submit'].includes(nextKey)
                            && ! this.feeGateSatisfied()) {
                            await this.refreshApplicationFeeQuote();
                        }
                        if (! this.supplementMode && nextKey && this.needsFeeGateBefore(nextKey)) {
                            this.feeGateOpen = true;
                            this.enterApplicationFeeStep();
                            this.scrollWizardIntoView();
                            return;
                        }
                        this.step++;
                        this.bumpFurthest(this.step);
                        this.syncStepKey();
                        this.enforceStepRequirements();
                        if (this.stepKey === 'review') {
                            this.reviewPage = 1;
                            this.refreshReview(this.formRoot());
                        }
                        if (this.stepKey === 'submit') {
                            this.resigningOnSubmit = ! this.borrowerSignature?.signature_data;
                            this.$nextTick(() => this.syncSubmitPayload(this.formRoot()));
                        }
                        this.scrollWizardIntoView();
                    } finally {
                        this.advancing = false;
                    }
                },

                prev() {
                    if (this.feeGateOpen) {
                        this.feeGateOpen = false;
                        this.scrollWizardIntoView();
                        return;
                    }
                    if (this.reviewBack()) return;
                    if (this.step > 0) {
                        this.feeGateOpen = false;
                        this.step--;
                        this.syncStepKey();
                        if (this.stepKey === 'review') {
                            this.reviewPage = this.reviewPageCount;
                            this.refreshReview(this.formRoot());
                        }
                        if (this.stepKey === 'signature') {
                            this.$nextTick(() => this.restoreSignaturePad());
                        }
                        this.scrollWizardIntoView();
                    }
                },

                goto(i) {
                    if (i <= (this.furthestStep ?? this.step)) {
                        this.feeGateOpen = false;
                        this.step = i;
                        this.syncStepKey();
                        if (this.stepKey === 'signature') {
                            this.$nextTick(() => this.restoreSignaturePad());
                        }
                        if (this.stepKey === 'submit') {
                            this.$nextTick(() => this.syncSubmitPayload(this.formRoot()));
                        }
                        this.scrollWizardIntoView();
                    }
                },

                restoreSignaturePad() {
                    const sig = this.borrowerSignature?.signature_data;
                    if (! sig) return;
                    const form = this.formRoot();
                    const pad = form?.querySelector('[data-signature-pad]');
                    const alpineData = pad?._x_dataStack?.[0];
                    alpineData?.loadFromDataUrl?.(sig);
                },

                startResignOnSubmit() {
                    this.resigningOnSubmit = true;
                    this.$nextTick(() => {
                        const form = this.formRoot();
                        const pad = form?.querySelector('[data-signature-pad]');
                        const alpineData = pad?._x_dataStack?.[0];
                        alpineData?.clear?.();
                    });
                },

                captureSubmitSignature(form) {
                    const fromPad = this.readSignatureFromPad(form);
                    if (fromPad) {
                        this.borrowerSignature = {
                            signer_name: this.verifiedLegalName || this.borrowerSignature?.signer_name || '',
                            signature_data: fromPad,
                            consent_accepted: true,
                            signed_at: new Date().toISOString(),
                        };
                        this.resigningOnSubmit = false;
                        this.declarationAccepted = true;
                        return fromPad;
                    }
                    return this.borrowerSignature?.signature_data
                        || this.profileSignature?.signature_data
                        || '';
                },

                readSignatureFromPad(form) {
                    const pad = form?.querySelector('[data-signature-pad]');
                    const alpineData = pad?._x_dataStack?.[0];
                    if (alpineData?.dataUrl) {
                        return alpineData.dataUrl;
                    }
                    return form?.querySelector('[data-submit-signature]')?.value || '';
                },

                syncSubmitPayload(form) {
                    if (! form) return;
                    const set = (selector, value) => {
                        const el = form.querySelector(selector);
                        if (el != null && value !== undefined && value !== null && value !== '') {
                            el.value = String(value);
                        }
                    };
                    const sigData = this.borrowerSignature?.signature_data || this.readSignatureFromPad(form) || '';
                    const signerName = (this.borrowerSignature?.signer_name || this.verifiedLegalName || '').trim();
                    set('[data-submit-signature]', sigData);
                    set('[data-submit-signer]', signerName);
                    set('[data-submit-product]', this.form.loan_product_id);
                    set('[data-submit-amount]', this.form.requested_amount);
                    set('[data-submit-tenure]', this.form.requested_tenure_months);
                    set('[data-submit-purpose]', this.normalizePurposeKey(this.form.purpose));
                    const purposeOtherEl = form.querySelector('[data-submit-purpose-other]');
                    if (purposeOtherEl) {
                        purposeOtherEl.value = this.isOtherPurpose(this.form.purpose)
                            ? String(this.form.purpose_other || '').trim()
                            : '';
                    }
                    set('[data-submit-guarantor-mode]', this.form.guarantor_mode);
                    [
                        'external_first_name', 'external_middle_name', 'external_last_name',
                        'external_phone', 'external_email', 'external_relationship',
                        'external_region', 'external_district', 'external_invitation_id',
                        'internal_member_no', 'internal_guarantor_phone', 'internal_guarantor_name',
                    ].forEach((key) => {
                        if (this.form[key] != null && this.form[key] !== '') {
                            set(`[name="${key}"]`, this.form[key]);
                        }
                    });
                },

                submitApplication() {
                    const form = this.formRoot();
                    if (! form) return;
                    this.onSubmit({ target: form, preventDefault() {} });
                },

                onSubmit(e) {
                    e.preventDefault();
                    if (this.stepKey !== 'submit') {
                        return;
                    }
                    if (! this.canApply) {
                        this.showProfileGateModal = true;
                        return;
                    }
                    if (this.isGroupProduct(this.current) && ! this.groupProgress().can_submit) {
                        showWizardFeedback(this.i18n.group.membersNotVerified);
                        return;
                    }
                    if (! this.declarationAccepted && ! this.supplementMode) {
                        showWizardFeedback(this.i18n.alerts.acceptTerms);
                        return;
                    }
                    const sigData = this.supplementMode
                        ? (this.borrowerSignature?.signature_data || this.profileSignature?.signature_data || 'supplement')
                        : this.captureSubmitSignature(e.target);
                    const signerName = (this.borrowerSignature?.signer_name
                        || this.profileSignature?.signer_name
                        || this.verifiedLegalName
                        || e.target.elements['signer_name']?.value
                        || '').trim();
                    if (! this.supplementMode && (! signerName || ! sigData)) {
                        showWizardFeedback(this.i18n.alerts.drawSignature);
                        this.resigningOnSubmit = true;
                        return;
                    }
                    this.syncSubmitPayload(e.target);
                    if (this.supplementMode && this.supplementApplicationId) {
                        let input = e.target.querySelector('input[name="supplement_application_id"]');
                        if (! input) {
                            input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'supplement_application_id';
                            e.target.appendChild(input);
                        }
                        input.value = String(this.supplementApplicationId);
                    }
                    this.submitting = true;
                    window.confirmForm(e.target, {
                        title: this.i18n.alerts.submitTitle,
                        message: this.i18n.alerts.submitMessage,
                        confirmLabel: this.i18n.submitConfirmLabel,
                        confirmClass: 'bg-gray-900 hover:bg-gray-800 text-white',
                        onCancel: () => { this.submitting = false; },
                    });
                },

                formatTzs(v, decimals = 0) {
                    return (window.formatMoney || ((x) => 'TZS ' + x))(v, { currency: 'TZS', decimals });
                },

                formatAmount(v, decimals = 0) {
                    if (typeof window.formatNumber === 'function') {
                        return window.formatNumber(v, decimals);
                    }
                    if (typeof window.formatMoney === 'function') {
                        return window.formatMoney(v, { currency: 'TZS', decimals, withCode: false });
                    }
                    return String(v ?? 0);
                },
            };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('applyWizard', (config) => applyWizard(config));
});
