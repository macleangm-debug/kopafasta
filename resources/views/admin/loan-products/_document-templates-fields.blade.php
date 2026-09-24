@php
    $r = $record ?? null;
    $isAssetLending = $isAssetLending ?? false;
    $templates = $documentTemplates ?? collect();
    $templateOptions = ['' => 'System default'] + $templates->mapWithKeys(fn ($t) => [(string) $t->id => $t->name.' ('.$t->code.')'])->all();
@endphp

<x-admin.step title="Agreement template">
    <p class="md:col-span-2 text-xs text-gray-500 mb-3">
        Templates stay in
        <a href="{{ route('admin.document-templates.index') }}" class="text-amber-700 font-semibold hover:underline">Document templates</a>.
        Here you only choose which one this product uses.
    </p>
    @if ($isAssetLending)
        <x-admin.select name="asset_lending_agreement_template_id" label="Agreement template" :options="$templateOptions" :value="(string) ($r?->asset_lending_agreement_template_id ?? '')" help="Leave blank to use the default Asset Lending agreement." />
        @if ($r)
            <p class="md:col-span-2 text-xs">
                <a href="{{ route('admin.document-templates.index', ['product' => $r->id]) }}" class="font-semibold text-amber-700 hover:underline">Manage document templates →</a>
            </p>
        @endif
        <input type="hidden" name="offer_letter_template_id" value="{{ $r?->offer_letter_template_id }}">
        <input type="hidden" name="loan_contract_template_id" value="{{ $r?->loan_contract_template_id }}">
        <input type="hidden" name="guarantor_agreement_template_id" value="{{ $r?->guarantor_agreement_template_id }}">
    @else
        <x-admin.select name="offer_letter_template_id" label="Offer letter template" :options="$templateOptions" :value="(string) ($r?->offer_letter_template_id ?? '')" />
        <x-admin.select name="loan_contract_template_id" label="Loan contract template" :options="$templateOptions" :value="(string) ($r?->loan_contract_template_id ?? '')" />
        <x-admin.select name="guarantor_agreement_template_id" label="Guarantor agreement template" :options="$templateOptions" :value="(string) ($r?->guarantor_agreement_template_id ?? '')" />
    @endif
</x-admin.step>
