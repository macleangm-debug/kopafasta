{{-- Signature step — identity confirmation then pad --}}
<div x-show="stepKey === 'signature' && ! $data.feeGateOpen" class="p-6 sm:p-8" data-signature-step>
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.signature')"
        :title="__('borrower.apply.signature_title')"
        :subtitle="__('borrower.apply.signature_subtitle')"
    />

    <div class="grid lg:grid-cols-5 gap-6">
        <div class="lg:col-span-2 space-y-4">
            <section class="glass-card overflow-hidden ring-1 ring-brand/15">
                <div class="kf-premium-panel px-5 py-5">
                    <p class="text-[10px] uppercase tracking-widest text-white/80">{{ __('borrower.apply.signature_legal_name') }}</p>
                    <p class="mt-2 text-xl font-bold" x-text="verifiedLegalName || '—'"></p>
                    <p x-show="identityVerified" x-cloak class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold bg-white/15 px-2.5 py-1 rounded-lg">
                        <span>✓</span> {{ __('borrower.apply.signature_verified') }}
                    </p>
                </div>
                <div class="px-5 py-4 text-sm text-gray-700 space-y-2">
                    <p class="font-semibold text-gray-900">{{ __('borrower.apply.signature_declaration') }}</p>
                    <p class="text-xs text-gray-500">{{ __('borrower.apply.profile_verify.subtitle') }}</p>
                </div>
            </section>

            <label class="flex items-start gap-3 text-sm text-gray-700 rounded-2xl bg-white ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-brand/30 transition">
                <input type="checkbox"
                       name="borrower_consent"
                       value="1"
                       x-model="declarationAccepted"
                       @change="persistDeclaration()"
                       class="mt-1 rounded border-gray-300 text-brand focus:ring-brand">
                <span>{{ __('borrower.apply.signature_consent', ['brand' => brand_name()]) }}</span>
            </label>

            <p x-show="declarationAccepted" x-cloak class="text-xs font-semibold text-emerald-700 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2"><path d="M5 10l3 3 7-7"/></svg>
                {{ __('borrower.apply.signature_declaration_saved') }}
            </p>
        </div>

        <div class="lg:col-span-3">
            <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/15 h-full"
                 :class="declarationAccepted ? '' : 'opacity-60 pointer-events-none'">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">{{ __('borrower.apply.signature_draw_label') }}</p>
                <x-site.signature-pad
                    :default-name="$verifiedLegalName"
                    :readonly-name="true"
                    :verified="$identityVerified"
                    :include-in-form="false" />
            </div>
        </div>
    </div>
</div>
