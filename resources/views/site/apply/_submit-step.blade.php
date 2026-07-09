{{-- Submit step --}}
<div x-show="stepKey === 'submit'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.submit')"
        :title="__('borrower.apply.submit_step.title')"
        :subtitle="__('borrower.apply.submit_step.subtitle')"
    />

    <div class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-5 py-5 mb-6">
        <div class="flex items-start gap-3">
            <span class="size-10 rounded-full bg-emerald-100 text-emerald-700 grid place-items-center text-lg shrink-0">✓</span>
            <div>
                <p class="text-sm font-semibold text-emerald-900">{{ __('borrower.apply.submit_step.signed_title') }}</p>
                <p class="text-sm text-emerald-800 mt-1">{{ __('borrower.apply.submit_step.signed_hint') }}</p>
            </div>
        </div>
    </div>

    <div x-show="borrowerSignature?.signature_data" x-cloak class="mb-6 glass-card p-5 ring-1 ring-gray-200/80">
        <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold mb-2">{{ __('borrower.apply.signature_draw_label') }}</p>
        <p class="text-sm font-semibold text-gray-900 mb-3" x-text="borrowerSignature?.signer_name || verifiedLegalName"></p>
        <img :src="borrowerSignature?.signature_data" alt="" class="max-h-32 border border-gray-200 rounded-xl bg-white">
    </div>

    <div x-show="draftReference" class="rounded-2xl bg-brand-muted/30 ring-1 ring-brand/15 px-5 py-4 text-sm text-gray-700 mb-6">
        {{ __('borrower.apply.submit_step.reference') }}:
        <span class="font-mono font-semibold text-gray-900" x-text="draftReference"></span>
    </div>

    <input type="hidden" name="signature_data" data-submit-signature>
    <input type="hidden" name="signer_name" data-submit-signer>
    <input type="hidden" name="consent" value="1">
</div>
