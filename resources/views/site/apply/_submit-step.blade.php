{{-- Submit step — signature-first, no duplicate deal/guarantor cards --}}
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

    {{-- Premium signature surface --}}
    <section class="relative overflow-hidden rounded-3xl ring-1 ring-brand/15 bg-white shadow-lg shadow-brand/10 mb-6"
             x-show="!supplementMode"
             x-cloak>
        <div class="absolute inset-x-0 top-0 h-28 bg-gradient-to-br from-brand via-brand to-brand-light" aria-hidden="true"></div>
        <div class="absolute inset-x-0 top-0 h-28 opacity-20" style="background-image: radial-gradient(circle at 15% 20%, #fff 0, transparent 40%), radial-gradient(circle at 85% 0%, #f5c842 0, transparent 35%);" aria-hidden="true"></div>

        <div class="relative px-5 sm:px-7 pt-6 pb-2">
            <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">{{ __('borrower.apply.signature_draw_label') }}</p>
            <p class="mt-2 text-xl sm:text-2xl font-bold text-white tracking-tight"
               x-text="verifiedLegalName || borrowerSignature?.signer_name || '—'"></p>
            <p x-show="identityVerified" x-cloak
               class="mt-2 inline-flex items-center gap-1.5 text-[11px] font-semibold bg-white/15 text-white px-2.5 py-1 rounded-lg">
                <span aria-hidden="true">✓</span> {{ __('borrower.apply.signature_verified') }}
            </p>
        </div>

        <div class="relative mx-4 sm:mx-6 mb-4 -mt-1 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 pt-4 pb-3 border-b border-gray-100">
                <label class="flex items-start gap-3 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox"
                           name="borrower_consent"
                           value="1"
                           x-model="declarationAccepted"
                           @change="persistDeclaration()"
                           class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                    <span class="leading-snug">{{ __('borrower.apply.signature_consent', ['brand' => brand_name()]) }}</span>
                </label>
            </div>

            <div class="p-4 sm:p-5"
                 :class="declarationAccepted ? '' : 'opacity-55 pointer-events-none'">
                {{-- Saved signature preview --}}
                <div x-show="borrowerSignature?.signature_data && !resigningOnSubmit" x-cloak class="space-y-3">
                    <div class="rounded-2xl bg-[linear-gradient(180deg,#f8faf9_0%,#ffffff_55%)] ring-1 ring-brand/10 px-3 py-4 min-h-[9rem] grid place-items-center">
                        <img :src="borrowerSignature.signature_data"
                             alt=""
                             class="max-h-28 w-auto max-w-full object-contain">
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold text-emerald-700 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2"><path d="M5 10l3 3 7-7"/></svg>
                            {{ __('borrower.apply.submit_step.signed_hint_short') }}
                        </p>
                        <button type="button"
                                @click="startResignOnSubmit()"
                                class="text-xs font-semibold text-brand hover:underline">
                            {{ __('borrower.apply.signature_clear') }}
                        </button>
                    </div>
                </div>

                {{-- Live pad when unsigned or resigning --}}
                <template x-if="!borrowerSignature?.signature_data || resigningOnSubmit">
                    <div>
                        <x-site.signature-pad
                            :default-name="$verifiedLegalName"
                            :readonly-name="true"
                            :verified="$identityVerified"
                            :include-in-form="false"
                            compact />
                    </div>
                </template>
            </div>
        </div>
    </section>

    <div x-show="draftReference" class="rounded-2xl bg-brand-muted/40 ring-1 ring-brand/10 px-5 py-3.5 text-sm text-gray-700">
        {{ __('borrower.apply.submit_step.reference') }}:
        <span class="font-mono font-semibold text-gray-900" x-text="draftReference"></span>
    </div>

    <input type="hidden" name="signature_data" data-submit-signature>
    <input type="hidden" name="signer_name" data-submit-signer>
    <input type="hidden" name="consent" value="1">
</div>
