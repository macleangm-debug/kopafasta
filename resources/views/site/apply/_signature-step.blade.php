{{-- Signature step --}}
<div x-show="stepKey === 'signature'" class="p-6 sm:p-8" data-signature-step>
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.signature')"
        :title="__('borrower.apply.signature_title')"
        :subtitle="__('borrower.apply.signature_subtitle')"
    />

    <div class="glass-card p-5 ring-1 ring-gray-200/80 mb-6">
        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.apply.signature_declaration') }}</p>
    </div>

    <label class="flex items-start gap-3 text-sm text-gray-700 mb-6 rounded-2xl bg-white ring-1 ring-gray-200 p-4 cursor-pointer hover:ring-brand/30 transition">
        <input type="checkbox"
               name="borrower_consent"
               value="1"
               x-model="declarationAccepted"
               @change="persistDeclaration()"
               class="mt-1 rounded border-gray-300 text-brand focus:ring-brand">
        <span>{{ __('borrower.apply.signature_consent', ['brand' => brand_name()]) }}</span>
    </label>

    <p x-show="declarationAccepted" x-cloak class="text-xs font-semibold text-emerald-700 mb-4 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2"><path d="M5 10l3 3 7-7"/></svg>
        {{ __('borrower.apply.signature_declaration_saved') }}
    </p>

    <div class="glass-card p-5 ring-1 ring-brand/15">
        <x-site.signature-pad
            :default-name="$verifiedLegalName"
            :readonly-name="true"
            :verified="$identityVerified"
            :include-in-form="false" />
    </div>
</div>
