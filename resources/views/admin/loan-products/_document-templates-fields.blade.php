@php
    $r = $record ?? null;
    $templates = $documentTemplates ?? collect();
    $templateOptions = ['' => 'System default PDF'] + $templates->mapWithKeys(fn ($t) => [(string) $t->id => $t->name.' ('.$t->code.')'])->all();
@endphp

<x-admin.step title="Document templates">
    <p class="md:col-span-2 text-xs text-gray-500 mb-2">Assign custom templates per product. Leave blank to use the built-in PDF layouts in <code class="text-[11px]">resources/views/pdf/</code>.</p>
    <x-admin.select name="offer_letter_template_id" label="Offer letter template" :options="$templateOptions" :value="(string) ($r?->offer_letter_template_id ?? '')" />
    <x-admin.select name="loan_contract_template_id" label="Loan contract template" :options="$templateOptions" :value="(string) ($r?->loan_contract_template_id ?? '')" />
    <x-admin.select name="guarantor_agreement_template_id" label="Guarantor agreement template" :options="$templateOptions" :value="(string) ($r?->guarantor_agreement_template_id ?? '')" />
    <x-admin.select name="asset_lending_agreement_template_id" label="Asset lending agreement template" :options="$templateOptions" :value="(string) ($r?->asset_lending_agreement_template_id ?? '')" />
</x-admin.step>
