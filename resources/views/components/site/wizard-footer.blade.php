{{-- Premium wizard footer. Expects Alpine parent: step, stepKey, prev(), backToDetails(), next(), etc. --}}
<div class="px-6 sm:px-8 py-4 border-t border-gray-200/80 bg-gradient-to-r from-brand-muted/30 to-white rounded-b-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <button type="button"
            @click="step > 0 ? prev() : backToDetails()"
            class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2"><path d="M12 4l-6 6 6 6"/></svg>
        <span x-text="step > 0 ? i18n.back : i18n.backProducts"></span>
    </button>
    <div class="flex flex-wrap items-center justify-end gap-3">
        <a href="{{ route('site.borrower.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('borrower.apply.cancel') }}</a>
        <button type="button"
                @click.prevent="next()"
                :disabled="advancing || resumeLoading || (guarantorInvitePreparing && stepKey === 'guarantor') || (stepKey === 'guarantor' && form.guarantor_mode === 'internal' && !internalGuarantorFieldsFilled()) || (stepKey === 'guarantor' && form.guarantor_mode === 'external' && !isExternalGuarantorComplete())"
                x-show="!['signature', 'submit'].includes(stepKey)"
                class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 disabled:opacity-60 text-brand font-bold px-6 py-2.5 rounded-xl text-sm shadow-sm transition">
            <span x-text="(guarantorInvitePreparing && stepKey === 'guarantor')
                ? @js(__('borrower.apply.application_fee.processing'))
                : (stepKey === 'review' && reviewPage < reviewPageCount
                    ? @js(__('borrower.apply.review_step.next_page'))
                    : @js(__('borrower.apply.continue')))"></span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2"><path d="M8 4l6 6-6 6"/></svg>
        </button>
        <button type="button"
                @click.prevent="signApplication()"
                :disabled="advancing || !declarationAccepted"
                x-show="stepKey === 'signature'"
                class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-6 py-2.5 rounded-xl text-sm shadow-sm transition">
            {{ __('borrower.apply.sign_application') }}
            <svg class="w-4 h-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2"><path d="M8 4l6 6-6 6"/></svg>
        </button>
        <button type="button"
                @click="submitApplication()"
                :disabled="submitting || advancing || !canApply"
                x-show="stepKey === 'submit'"
                class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-6 py-2.5 rounded-xl text-sm shadow-sm transition">
            <span x-text="submitting ? @js(__('borrower.apply.submitting')) : (!canApply ? @js(__('borrower.apply.complete_profile_to_submit')) : @js(__('borrower.apply.submit')))"></span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2"><path d="M8 4l6 6-6 6"/></svg>
        </button>
    </div>
</div>
