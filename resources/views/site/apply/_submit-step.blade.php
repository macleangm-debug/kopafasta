{{-- Submit step — final confirmation (premium brand treatment) --}}
<div x-show="stepKey === 'submit'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.submit')"
        :title="__('borrower.apply.submit_step.title')"
        :subtitle="__('borrower.apply.submit_step.subtitle')"
    />

    <div x-show="!canApply" x-cloak class="mb-6 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.kyc_incomplete_title') }}</p>
            <p class="text-sm text-white/90 mt-1">{{ __('borrower.apply.kyc_incomplete_submit_hint') }}</p>
        </div>
        <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-gray-600">{{ __('borrower.apply.kyc_incomplete_submit') }}</p>
            <button type="button"
                    @click="showProfileGateModal = true"
                    class="inline-flex justify-center shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                {{ __('borrower.loan_profile.complete_profile') }}
            </button>
        </div>
    </div>

    <div x-show="supplementMode" x-cloak class="glass-card rounded-2xl ring-1 ring-sky-200 bg-gradient-to-br from-sky-50 to-white px-5 py-4 text-sm text-sky-900 mb-6">
        <p class="font-semibold">{{ __('borrower.apply.submit_step.supplement_title') }}</p>
        <p class="mt-1 text-sky-800">{{ __('borrower.apply.submit_step.supplement_hint') }}</p>
    </div>

    <div x-show="canApply && !supplementMode" x-cloak
         class="mb-6 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-gradient-to-br from-brand via-brand to-brand-light text-white shadow-sm">
        <div class="px-5 sm:px-6 py-5 flex items-start gap-4">
            <span class="size-12 rounded-full bg-brand-gold text-brand grid place-items-center text-xl font-bold shrink-0 shadow-sm">✓</span>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.steps.submit') }}</p>
                <p class="text-lg font-bold mt-1">{{ __('borrower.apply.submit_step.signed_title') }}</p>
                <p class="text-sm text-white/85 mt-1">{{ __('borrower.apply.submit_step.signed_hint') }}</p>
            </div>
        </div>
    </div>

    <section class="glass-card overflow-hidden ring-1 ring-brand/15 mb-6">
        <div class="bg-gradient-to-r from-brand-muted/40 to-white px-5 py-3 border-b border-brand/10 flex items-center justify-between gap-3">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.submit_step.summary_title') }}</p>
            <button type="button"
                    x-show="hasStep('quote') || hasStep('asset_tenure') || hasStep('asset_details')"
                    @click="gotoKey(hasStep('asset_details') ? 'asset_details' : (hasStep('quote') ? 'quote' : 'asset_tenure'), { returnTo: 'submit' })"
                    class="text-xs font-semibold text-brand hover:underline shrink-0">
                {{ __('borrower.apply.submit_step.edit_quote') }}
            </button>
        </div>
        <dl class="grid sm:grid-cols-2 text-sm">
            <div class="px-5 py-4 border-b sm:border-r border-gray-100">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.review_step.product') }}</dt>
                <dd class="mt-1 font-semibold text-gray-900" x-text="current?.name || '—'"></dd>
            </div>
            <div class="px-5 py-4 border-b border-gray-100">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.review_step.loan_amount') }}</dt>
                <dd class="mt-1 font-semibold tabular-nums text-gray-900" x-text="formatTzs(form.requested_amount)"></dd>
                <dd class="text-[11px] text-gray-500 mt-0.5" x-show="isAssetBackedProduct(current)">{{ __('borrower.apply.asset_details.request_label') }}</dd>
            </div>
            <div class="px-5 py-4 sm:border-r border-gray-100">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.review_step.duration') }}</dt>
                <dd class="mt-1 font-semibold text-gray-900"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.browse.months_short') }}</dd>
                <dd class="text-[11px] text-gray-500 mt-0.5" x-show="isAssetBackedProduct(current)">{{ __('borrower.apply.asset_details.request_label') }}</dd>
            </div>
            <div class="px-5 py-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500" x-text="repaymentCadence() === 'monthly' ? @js(__('borrower.apply.review_step.monthly_repayment')) : @js(__('borrower.apply.review_step.weekly_repayment'))"></dt>
                <dd class="mt-1 font-semibold text-brand" x-show="isAssetBackedProduct(current)">{{ __('borrower.apply.asset_details.repayment_pending_offer') }}</dd>
                <dd class="mt-1 font-semibold tabular-nums text-brand" x-show="!isAssetBackedProduct(current)" x-text="formatTzs(reviewSummary.installment_amount ?? quote.primary ?? quote.monthly)"></dd>
            </div>
        </dl>
    </section>

    <div x-show="hasStep('guarantor') && form.guarantor_mode && form.guarantor_mode !== 'none'" x-cloak
         class="mb-6 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 py-4 border-b border-brand/10">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.review_step.guarantor_section') }}</p>
            <p class="text-base font-semibold text-gray-900 mt-1" x-text="review.guarantorName || guarantorSummaryText()"></p>
        </div>
        <div class="px-5 py-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900" x-text="review.guarantorStatus || @js(__('borrower.apply.submit_step.guarantor_pending_title'))"></p>
                <p class="text-sm text-gray-600 mt-1">{{ __('borrower.apply.submit_step.guarantor_pending_hint') }}</p>
            </div>
            <div class="flex flex-col gap-2 shrink-0 w-full sm:w-auto">
                <button type="button"
                        @click="gotoKey('guarantor', { returnTo: 'submit' })"
                        class="inline-flex justify-center bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-4 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.apply.submit_step.view_guarantor') }}
                </button>
                <button type="button"
                        @click="gotoKey('guarantor', { returnTo: 'submit' }); $nextTick(() => { if (isGuarantorLocked()) changeGuarantor(); })"
                        :disabled="guarantorChanging"
                        class="inline-flex justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm disabled:opacity-60">
                    {{ __('borrower.apply.change_guarantor') }}
                </button>
            </div>
        </div>
    </div>

    <div x-show="borrowerSignature?.signature_data" x-cloak
         class="mb-6 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white">
        <div class="bg-gradient-to-r from-brand-muted/40 to-white px-5 py-3 border-b border-brand/10">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.signature_draw_label') }}</p>
            <p class="text-sm font-semibold text-gray-900 mt-0.5" x-text="borrowerSignature?.signer_name || verifiedLegalName"></p>
        </div>
        <div class="px-5 py-4">
            <img :src="borrowerSignature?.signature_data" alt="" class="max-h-32 border border-gray-200 rounded-xl bg-white">
        </div>
    </div>

    <div x-show="draftReference" class="glass-card rounded-2xl bg-brand-muted/30 ring-1 ring-brand/15 px-5 py-4 text-sm text-gray-700 mb-6">
        {{ __('borrower.apply.submit_step.reference') }}:
        <span class="font-mono font-semibold text-gray-900" x-text="draftReference"></span>
    </div>

    <input type="hidden" name="signature_data" data-submit-signature>
    <input type="hidden" name="signer_name" data-submit-signer>
    <input type="hidden" name="consent" value="1">
</div>
