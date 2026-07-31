
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
                groupMemberStatusesUrl: config.groupMemberStatusesUrl || '',
                previousGroupMembersUrl: config.previousGroupMembersUrl || '',
                selectPreviousGroupMemberUrl: config.selectPreviousGroupMemberUrl || '',
                groupLimits: config.groupLimits || { min: 5, max: 30 },
                leaderCustomerId: config.leaderCustomerId || null,
                leaderName: config.leaderName || '',
                leaderPhone: config.leaderPhone || '',
                group: config.savedDraft?.group || { name: '', purpose: '', target_member_count: null, amount_per_member: 0, members: [] },
                groupMemberMode: 'internal',
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
                guarantorInvitePreparing: false,
                advancing: false,
                submitting: false,
                resumeLoading: false,
                furthestStep: 0,
                showProfileGateModal: false,
                openProfileGateOnLoad: !!(config.openProfileGateOnLoad),
                isResume: !! config.isResume,
                guarantorErrors: {},
                externalInviteTimer: null,
                initialPlan: config.initialPlan || [],
                assetApplication: config.assetApplication || null,
                reservationMode: !! config.reservationMode,
                marketplaceOnlyCodes: config.marketplaceOnlyCodes || [],
                marketplaceUrl: config.marketplaceUrl || '',
                profileUrl: config.profileUrl || '',
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
                review: { personal: '', residence: '', employment: '', nok: '', activity: '', guarantor: '', guarantorType: '', guarantorName: '', guarantorStatus: '' },
                reviewSummary: { monthly_rate_pct: 0, application_fee: 0, monthly_installment: 0, installment_amount: 0, repayment_cadence: 'monthly' },
                repaymentSchedule: [],
                scheduleDatesAvailable: false,
                scheduleLoading: false,
                reviewPage: 1,
                reviewPageCount: 3,
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
                    this.$watch('phase', (value, oldValue) => {
                        this.scheduleDraftSave();
                        if (value === 'application' && oldValue !== 'application') {
                            this.persistDraft(true);
                        }
                    });
                    this.$watch('step', () => {
                        this.bumpFurthest(this.step);
                        this.scheduleDraftSave();
                        this.syncStepKey();
                    });
                    this.$watch('stepKey', (key) => {
                        if (key === 'application_fee') {
                            this.enterApplicationFeeStep();
                        }
                        if (key === 'guarantor') {
                            this.loadPreviousGuarantors();
                        }
                        if (key === 'group_members') {
                            this.loadPreviousGroupMembers();
                            this.refreshGroupMemberStatuses();
                        }
                        if (key === 'guarantor' && this.externalGuarantor?.invitation_id) {
                            this.refreshExternalGuarantorStatus();
                        }
                        if (key === 'signature') {
                            this.$nextTick(() => this.restoreSignaturePad());
                        }
                        if (key === 'submit') {
                            this.$nextTick(() => this.syncSubmitPayload(this.formRoot()));
                        }
                    });
                    this.$watch('steps', () => this.syncStepKey());
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
                                inputs[key] = value;
                            }
                        }
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
                        || ['paid', 'waived', 'pending'].includes(this.applicationFeeState?.status || '');
                },

                feeGateRequiredForStep(targetStepKey) {
                    const feeIdx = this.steps.findIndex(s => s.key === 'application_fee');
                    const targetIdx = this.steps.findIndex(s => s.key === targetStepKey);
                    return feeIdx >= 0 && targetIdx > feeIdx && this.effectiveFeeAmount() > 0;
                },

                enforceStepRequirements(onResume = false) {
                    if (this.supplementMode) return;
                    const feeIdx = this.steps.findIndex(s => s.key === 'application_fee');
                    if (feeIdx < 0 || this.effectiveFeeAmount() <= 0 || this.feeGateSatisfied()) return;
                    const currentIdx = this.steps.findIndex(s => s.key === this.stepKey);
                    if (currentIdx <= feeIdx) return;
                    if (onResume && this.applicationFeeState?.status) return;
                    if (currentIdx > feeIdx) {
                        this.step = feeIdx;
                        this.syncStepKey();
                    }
                },

                syncQuoteFormFromDom() {
                    const purpose = this.readFormField('purpose');
                    if (purpose) this.form.purpose = purpose;
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
                        || ['paid', 'waived', 'pending'].includes(this.valuationFeeState?.status || '');
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
                        const feeCode = this.feePromoCode
                            ? String(this.feePromoCode).trim().toUpperCase()
                            : null;
                        const body = {
                            loan_product_id: this.form.loan_product_id,
                            channel: this.feeChannel || 'mobile_money',
                            payment_phone: this.feePhone || '',
                            use_wallet: !!this.feeUseWallet,
                            promo_code: feeCode,
                            affiliate_code: feeCode,
                            redeem_loyalty: !!(this.feeRedeemLoyalty && this.feeLoyaltyOption?.can_redeem),
                            loyalty_option_key: this.feeLoyaltyOption?.key || null,
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
                        this.feeNotice = {
                            tone: 'success',
                            message: data.message || this.i18n.applicationFee.paid,
                        };
                        // Auto-advance without interrupting with alert().
                        this.feePaying = false;
                        try {
                            await this.next();
                        } catch (advanceErr) {
                            console.warn('post-fee advance failed', advanceErr);
                        }
                    } catch (e) {
                        this.feeNotice = {
                            tone: 'error',
                            message: e?.message || this.i18n.applicationFee.failed,
                        };
                    } finally {
                        this.feePaying = false;
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
                    if (draft.borrower_signature) this.borrowerSignature = draft.borrower_signature;
                    if (draft.declaration_accepted || draft.borrower_signature) this.declarationAccepted = true;
                    if (draft.group) this.group = draft.group;
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
                        if (draft.borrower_signature?.signature_data) {
                            const sigIdx = this.steps.findIndex(s => s.key === 'signature');
                            const submitIdx = this.steps.findIndex(s => s.key === 'submit');
                            furthest = Math.max(furthest, sigIdx, submitIdx);
                        }
                        this.furthestStep = furthest;
                        this.step = viewStep;
                        this.updateQuote();
                        this.syncStepKey();
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
                        this.syncGroupAmounts();
                        return;
                    }
                    this.group.members = [{
                        customer_id: this.leaderCustomerId,
                        name: this.leaderName,
                        phone: this.leaderPhone,
                        role: 'leader',
                        requested_amount: this.group.amount_per_member,
                    }];
                    this.syncGroupAmounts();
                },

                groupTargetCount() {
                    return Number(this.group.target_member_count || this.groupLimits.min || 0);
                },

                groupAmountPerMemberMin() {
                    const totalMin = Number(this.current?.min || 1000);
                    const members = Math.max(this.groupLimits.min, this.groupTargetCount() || this.groupLimits.min);
                    return Math.max(1000, Math.ceil(totalMin / members));
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
                    const added = this.group.members.length;
                    const verified = this.group.members.filter(m => (m.status_key || '') === 'kyc_complete').length;
                    const profiles = this.group.members.filter(m => ['profile_complete', 'kyc_complete'].includes(m.status_key || '')).length;
                    const invitationsPending = this.group.members.filter(m => [
                        'invitation_sent', 'link_opened', 'registration_started', 'registration_complete', 'profile_incomplete',
                    ].includes(m.status_key || (m.invitation_id ? 'invitation_sent' : ''))).length;
                    const tpl = this.i18n.groupProgress || {};
                    const fill = (text, vars) => Object.entries(vars).reduce((s, [k, v]) => s.replace(':' + k, String(v)), text || '');
                    return {
                        target,
                        added,
                        verified,
                        profiles_complete: profiles,
                        invitations_pending: invitationsPending,
                        summary: [
                            fill(tpl.added, { added, target }),
                            fill(tpl.profiles, { done: profiles, target }),
                            fill(tpl.verified, { done: verified, target }),
                            fill(tpl.invitations_pending, { count: invitationsPending }),
                        ],
                        can_submit: target > 0 && added === target && verified === target,
                    };
                },

                memberStatusLabel(member) {
                    const key = member.status_key || (member.invitation_id ? 'invitation_sent' : 'profile_incomplete');
                    return this.groupProgressLabels?.[key] || key;
                },

                memberStatusClass(member) {
                    const key = member.status_key || (member.invitation_id ? 'invitation_sent' : 'profile_incomplete');
                    return key === 'kyc_complete' ? 'text-emerald-700' : 'text-brand';
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
                            if (data.summary) {
                                this.groupProgressSummary = data.summary;
                            }
                            if (data.application_status) {
                                this.groupApplicationStatus = data.application_status;
                            }
                            if (data.scoring) {
                                this.groupScoring = data.scoring;
                            }
                            if (Array.isArray(data.members)) {
                                this.group.members = data.members;
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
                            this.groupLookupError = data.message || this.i18n.group.lookupNotFound;
                            return;
                        }
                        this.groupExternalInvite = data.share;
                        this.group.members.push({
                            invitation_id: data.invitation_id || data.share?.invitation_id,
                            name: data.name,
                            phone: data.phone,
                            role: 'member',
                            requested_amount: this.group.amount_per_member,
                            status_key: 'invitation_sent',
                        });
                        this.groupExternal = { first_name: '', last_name: '', phone: '' };
                        this.syncGroupAmounts();
                        this.groupProgressSummary = null;
                        await this.persistDraft(true);
                    } catch (e) {
                        this.groupLookupError = this.i18n.group.lookupNotFound;
                    } finally {
                        this.groupInviteLoading = false;
                    }
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
                        });
                        this.updateGroupTotal();
                        await this.persistDraft(true);
                    } catch (e) {
                        this.groupLookupError = this.i18n.group.lookupNotFound;
                    } finally {
                        this.groupLookupLoading = false;
                    }
                },

                async lookupGroupMember() {
                    if (! this.groupMemberLookupUrl) return;
                    this.groupLookupError = '';
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
                            }),
                        });
                        const data = await res.json();
                        if (! res.ok || ! data.ok) {
                            this.groupLookupError = data.message || this.i18n.group.lookupNotFound;
                            return;
                        }
                        if (this.group.members.some(m => Number(m.customer_id) === Number(data.customer_id))) {
                            this.groupLookupError = this.i18n.groupMembers.duplicate;
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
                        });
                        this.groupLookupMemberNo = '';
                        this.groupLookupPhone = '';
                        this.groupProgressSummary = null;
                        this.updateGroupTotal();
                        await this.persistDraft(true);
                    } catch (e) {
                        this.groupLookupError = this.i18n.group.lookupNotFound;
                    } finally {
                        this.groupLookupLoading = false;
                    }
                },

                removeGroupMember(index) {
                    const member = this.group.members[index];
                    if (! member || member.role === 'leader') return;
                    this.group.members.splice(index, 1);
                    this.updateGroupTotal();
                },

                beginReservationApplication() {
                    const p = this.products.find(x => x.id == config.preselect);
                    if (! p) return;
                    this.selectProduct(p, true);
                    this.form.requested_amount = this.assetApplication.remaining_loan;
                    this.form.requested_tenure_months = this.assetApplication.max_tenure_months;
                    this.form.purpose = this.assetApplication.purpose || 'asset_financing';
                    this.phase = 'application';
                    this.step = 0;
                    this.syncStepKey();
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

                async startApplication() {
                    if (! this.current) return;
                    const productId = this.current.id;
                    if (! this.readiness || this.readiness?.product?.id !== productId) {
                        await this.loadReadiness(productId);
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
                    await this.persistDraft(true);
                    this.scrollWizardIntoView();
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
                        } else if (this.isAssetBackedProduct(this.current)) {
                            steps.push({ key: 'asset_details', label: stepLabels.asset_details || this.i18n.steps.asset_details });
                        } else if (! this.isMarketplaceProduct(this.current)) {
                            steps.push({ key: 'quote', label: stepLabels.quote });
                        } else {
                            steps.push({ key: 'asset_tenure', label: stepLabels.asset_tenure || stepLabels.quote });
                        }
                        steps.push({ key: 'application_fee', label: this.i18n.steps.application_fee });
                        if (this.requiresGuarantor()) {
                            steps.push({ key: 'guarantor', label: this.i18n.steps.guarantor });
                        }
                        if (this.current?.code && this.productQuestions[this.current.code]) {
                            steps.push({ key: 'product_questions', label: stepLabels.product_questions });
                        }
                        steps.push({ key: 'review', label: this.i18n.steps.review });
                        steps.push({ key: 'submit', label: this.i18n.steps.submit });
                        this.steps = steps.map(s => this.withStepIcon(s));
                    }
                    this.step = this.resolveStepIndex(prevKey, this.step);
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
                    const periods = Math.max(1, Math.round(months * 4.33));
                    const periodRate = rate / 4;
                    return Math.round((principal / periods) + (principal * periodRate));
                },

                repaymentCadence() {
                    const freq = (this.current?.frequency || 'weekly').toLowerCase();
                    return freq === 'monthly' ? 'monthly' : 'weekly';
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
                    const principal = this.form.requested_amount;
                    const cadence = this.repaymentCadence();
                    const monthly = this.estimateEmi(principal, rate, months);
                    const weekly = this.estimateWeeklyInstallment(principal, rate, months);
                    const primary = cadence === 'monthly' ? monthly : weekly;
                    const periods = cadence === 'monthly' ? months : Math.max(1, Math.round(months * 4.33));
                    const interest = Math.max(0, (primary * periods) - principal);
                    this.quote = {
                        monthly,
                        weekly,
                        primary,
                        frequency: cadence,
                        interest,
                        fees: this.applicationFee,
                        total: (primary * periods) + this.applicationFee,
                    };
                    if (this.phase === 'application') {
                        this.rebuildSteps();
                    }
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
                        this.form.guarantor_mode = 'internal';
                        this.form.previous_guarantor_id = id;
                        this.guarantorLookup = { ok: true, ...(data.lookup || {}) };
                        if (data.lookup?.member_no) {
                            this.form.internal_member_no = String(data.lookup.member_no).replace(/^KPF-TZ-/i, '');
                        }
                        if (data.lookup?.name) {
                            this.form.internal_guarantor_name = data.lookup.name;
                        }
                    } finally {
                        this.guarantorLookup.loading = false;
                    }
                },

                gotoKey(key) {
                    const i = this.steps.findIndex(s => s.key === key);
                    if (i >= 0 && i <= (this.furthestStep ?? this.step)) {
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
                    if (this.form.guarantor_mode === 'internal') {
                        return this.i18n.alerts.guarantorStatus?.internal_validated
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

                    return 'pending_acceptance';
                },

                guarantorLockedSummaryText() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected') {
                        return this.i18n.guarantorLocked.declined;
                    }

                    return this.i18n.guarantorLocked.summary;
                },

                guarantorLockedCardClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'bg-rose-50 ring-rose-200';
                    }
                    if (code === 'accepted' || code === 'guarantee_pending') {
                        return 'bg-emerald-50 ring-emerald-200';
                    }

                    return 'bg-amber-50 ring-amber-200';
                },

                guarantorLockedCardTextClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'text-rose-900';
                    }
                    if (code === 'accepted' || code === 'guarantee_pending') {
                        return 'text-emerald-900';
                    }

                    return 'text-amber-900';
                },

                guarantorLockedCardMutedClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'text-rose-700';
                    }
                    if (code === 'accepted' || code === 'guarantee_pending') {
                        return 'text-emerald-700';
                    }

                    return 'text-brand';
                },

                guarantorLockedCardBodyClass() {
                    const code = this.guarantorStatusCode();
                    if (code === 'rejected' || code === 'expired') {
                        return 'text-rose-800';
                    }
                    if (code === 'accepted' || code === 'guarantee_pending') {
                        return 'text-emerald-800';
                    }

                    return 'text-amber-800';
                },

                guarantorStatusBadgeClass() {
                    if (this.form.guarantor_mode === 'internal') {
                        return 'bg-amber-100 text-amber-900 ring-amber-200';
                    }

                    const code = this.guarantorStatusCode();

                    if (code === 'accepted') {
                        return 'bg-emerald-100 text-emerald-900 ring-emerald-200';
                    }
                    if (code === 'rejected' || code === 'expired') {
                        return 'bg-rose-100 text-rose-900 ring-rose-200';
                    }
                    if (code === 'guarantee_pending') {
                        return 'bg-violet-100 text-violet-900 ring-violet-200';
                    }
                    if (code === 'kyc_in_progress' || code === 'registration_in_progress') {
                        return 'bg-amber-100 text-amber-900 ring-amber-200';
                    }

                    return 'bg-sky-100 text-sky-900 ring-sky-200';
                },

                guarantorReviewStatus() {
                    return this.guarantorStatusLabel();
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
                            this.reviewSummary = {
                                monthly_rate_pct: data.summary?.monthly_rate_pct ?? 0,
                                application_fee: data.summary?.application_fee ?? this.applicationFee,
                                monthly_installment: data.summary?.monthly_installment ?? this.quote.monthly,
                                installment_amount: data.summary?.installment_amount ?? this.quote.primary ?? this.quote.monthly,
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
                    if (this.reviewPage >= 3) {
                        this.loadRepaymentSchedule();
                    }
                },

                setReviewPage(page) {
                    const next = Math.min(this.reviewPageCount, Math.max(1, Number(page) || 1));
                    this.reviewPage = next;
                    if (next >= 3) {
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

                async refreshExternalGuarantorStatus() {
                    if (! this.guarantorStatusUrl || ! this.externalGuarantor?.invitation_id) {
                        return;
                    }
                    try {
                        const params = new URLSearchParams({
                            invitation_id: String(this.externalGuarantor.invitation_id),
                        });
                        const res = await fetch(`${this.guarantorStatusUrl}?${params}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.ok && data.share) {
                            this.externalGuarantor = {
                                ...this.externalGuarantor,
                                ...data.share,
                            };
                        }
                    } catch {
                        // Non-blocking refresh.
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
                        if (! this.form.purpose) {
                            showWizardFeedback(this.i18n.alerts.selectPurpose);
                            return false;
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
                        if (! this.group.amount_per_member || Number(this.group.amount_per_member) < 1000) {
                            showWizardFeedback(this.i18n.group.amountRequired);
                            return false;
                        }
                        if (! this.group.purpose) {
                            showWizardFeedback(this.i18n.group.purposeRequired);
                            return false;
                        }
                        this.syncGroupAmounts();
                        this.form.purpose = this.group.purpose;
                    }
                    if (this.stepKey === 'group_members' && this.hasStep('group_members')) {
                        await this.refreshGroupMemberStatuses();
                        const target = this.groupTargetCount();
                        if (this.group.members.length !== target) {
                            showWizardFeedback(this.i18n.group.membersRequired);
                            return false;
                        }
                        const invalidAmount = this.group.members.some(m => ! m.requested_amount || Number(m.requested_amount) < 1000);
                        if (invalidAmount) {
                            showWizardFeedback(this.i18n.group.amountRequired);
                            return false;
                        }
                        const total = this.group.members.reduce((sum, m) => sum + Number(m.requested_amount || 0), 0);
                        if (this.current && (total < this.current.min || total > this.current.max)) {
                            showWizardFeedback(`Total group amount must be between ${this.formatTzs(this.current.min)} and ${this.formatTzs(this.current.max)}.`);
                            return false;
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
                        const missingInsurance = this.selectedCustomerAssetIds().some((id) => {
                            const asset = (this.customerAssets || []).find(a => String(a.id) === String(id));
                            return asset && asset.asset_type === 'vehicle' && ! asset.has_insurance;
                        });
                        if (missingInsurance) {
                            showWizardFeedback(this.i18n.assetDetails.vehicleNeedsInsurance || this.i18n.assetDetails.assetRequired);
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
                    }
                    if (this.stepKey === 'guarantor' && this.hasStep('guarantor')) {
                        this.syncGuarantorFormFromDom();
                        if (! this.form.guarantor_mode || this.form.guarantor_mode === 'none') {
                            showWizardFeedback(this.i18n.alerts.selectGuarantor);
                            return false;
                        }
                        if (this.form.guarantor_mode === 'internal' || this.form.guarantor_mode === 'previous') {
                            if (! this.internalGuarantorValidated()) {
                                this.guarantorLookup = {
                                    ...this.guarantorLookup,
                                    ok: false,
                                    error: this.i18n.alerts.guarantor_validate_first,
                                };
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
                                    this.scrollWizardIntoView();
                                    return false;
                                }
                                this.guarantorInviteError = this.i18n.alerts.guarantor_external_invite_required
                                    || this.i18n.alerts.guarantor_validate_first;
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
                                this.feeNotice = {
                                    tone: 'error',
                                    message: this.i18n.applicationFee.requiredBeforeContinue,
                                };
                                return false;
                            }
                        } else if (! this.applicationFeePaid) {
                            await this.autoWaiveApplicationFeeIfNeeded();
                        }
                    }
                    if (! this.supplementMode && ! this.feeGateSatisfied() && this.feeGateRequiredForStep(this.stepKey)) {
                        this.enforceStepRequirements();
                        this.feeNotice = {
                            tone: 'error',
                            message: this.i18n.applicationFee.requiredBeforeContinue,
                        };
                        return false;
                    }
                    const nextKey = this.steps[this.step + 1]?.key;
                    if (! this.supplementMode && nextKey && this.feeGateRequiredForStep(nextKey) && ! this.feeGateSatisfied()) {
                        this.feeNotice = {
                            tone: 'error',
                            message: this.i18n.applicationFee.requiredBeforeContinue,
                        };
                        return false;
                    }
                    return true;
                },

                internalGuarantorValidated() {
                    this.syncGuarantorFormFromDom();
                    const member = this.readFormField('internal_member_no');
                    const phone = this.readFormField('internal_guarantor_phone');
                    const name = this.readFormField('internal_guarantor_name');

                    return this.guarantorLookup.ok
                        && this.guarantorLookup.memberKey === member
                        && this.guarantorLookup.phone === phone
                        && this.guarantorLookup.name === name;
                },

                async validateInternalGuarantor() {
                    this.syncGuarantorFormFromDom();
                    const member = this.readFormField('internal_member_no');
                    const phone = this.readFormField('internal_guarantor_phone');
                    const name = this.readFormField('internal_guarantor_name');
                    const errors = {};
                    if (! member) {
                        errors.internal_member_no = this.i18n.alerts.guarantor_membership;
                    }
                    if (! phone) {
                        errors.internal_guarantor_phone = this.i18n.alerts.guarantor_phone;
                    }
                    if (! name) {
                        errors.internal_guarantor_name = this.i18n.alerts.guarantor_name;
                    }
                    if (Object.keys(errors).length) {
                        this.setGuarantorFieldErrors(errors);
                        this.scrollWizardIntoView();
                        return;
                    }
                    this.guarantorErrors = {};
                    this.guarantorValidating = true;
                    this.guarantorLookup = { ok: false, label: '', error: '', memberKey: member, phone, name };
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
                            body: JSON.stringify({ membership_no: member, phone, name }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.ok) {
                            this.guarantorLookup.error = data.message || this.i18n.alerts.guarantor_lookup_failed;
                            return;
                        }
                        this.guarantorLookup = {
                            ok: true,
                            label: data.label || data.name,
                            error: '',
                            memberKey: member,
                            phone,
                            name,
                        };
                    } catch {
                        this.guarantorLookup.error = this.i18n.alerts.guarantor_lookup_failed;
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
                        // Edit Amount / Edit Guarantor: return to Review/Submit instead of restarting.
                        const returnKey = this.returnTo;
                        const fromEdit = ['quote', 'asset_details', 'asset_tenure', 'group_setup', 'guarantor'].includes(this.stepKey);
                        if (returnKey && fromEdit && this.steps.some(s => s.key === returnKey)) {
                            this.gotoKey(returnKey);
                            this.returnTo = null;
                            return;
                        }
                        this.step++;
                        this.bumpFurthest(this.step);
                        this.syncStepKey();
                        this.enforceStepRequirements();
                        if (this.stepKey === 'application_fee') {
                            this.enterApplicationFeeStep();
                        }
                        if (this.stepKey === 'review') {
                            this.reviewPage = 1;
                            this.refreshReview(this.formRoot());
                        }
                        if (this.stepKey === 'submit' && ! this.borrowerSignature?.signature_data) {
                            if (this.profileUrl) {
                                window.location.href = this.profileUrl.includes('focus=signature')
                                    ? this.profileUrl
                                    : (this.profileUrl + (this.profileUrl.includes('?') ? '&' : '?') + 'section=personal&focus=signature');
                                return;
                            }
                        }
                        this.scrollWizardIntoView();
                    } finally {
                        this.advancing = false;
                    }
                },

                prev() {
                    if (this.reviewBack()) return;
                    if (this.step > 0) {
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
                    set('[data-submit-purpose]', this.form.purpose);
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
                    const sigData = this.borrowerSignature?.signature_data
                        || this.profileSignature?.signature_data
                        || this.readSignatureFromPad(e.target);
                    const signerName = (this.borrowerSignature?.signer_name
                        || this.profileSignature?.signer_name
                        || this.verifiedLegalName
                        || e.target.elements['signer_name']?.value
                        || '').trim();
                    if (! signerName || ! sigData) {
                        const target = this.profileUrl || '/borrower/profile';
                        window.location.href = target.includes('focus=signature')
                            ? target
                            : (target + (target.includes('?') ? '&' : '?') + 'section=personal&focus=signature');
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
