@php
    $r = $record ?? null;
    $templates = $documentTemplates ?? collect();
    $templateOptions = ['' => 'System default PDF'] + $templates->mapWithKeys(fn ($t) => [(string) $t->id => $t->name.' ('.$t->code.')'])->all();
@endphp

<x-admin.step title="Document templates">
    <p class="md:col-span-2 text-xs text-gray-500 mb-3">
        <strong>What admins do:</strong> create reusable agreement layouts under
        <a href="{{ route('admin.document-templates.index') }}" class="text-amber-700 font-semibold hover:underline">Document templates</a>
        (Blade/HTML with placeholders such as <code class="text-[11px]">{{ '{{ $customer_name }}' }}</code>,
        <code class="text-[11px]">{{ '{{ $principal }}' }}</code>, <code class="text-[11px]">{{ '{{ $repayment_schedule }}' }}</code>).
        Here you only <em>assign</em> which template each product uses for offer letters and contracts.
        Leave blank to use the built-in PDFs in <code class="text-[11px]">resources/views/pdf/</code>.
    </p>
    <x-admin.select name="offer_letter_template_id" label="Offer letter template" :options="$templateOptions" :value="(string) ($r?->offer_letter_template_id ?? '')" />
    <x-admin.select name="loan_contract_template_id" label="Loan contract template" :options="$templateOptions" :value="(string) ($r?->loan_contract_template_id ?? '')" />
    <x-admin.select name="guarantor_agreement_template_id" label="Guarantor agreement template" :options="$templateOptions" :value="(string) ($r?->guarantor_agreement_template_id ?? '')" />
    <x-admin.select name="asset_lending_agreement_template_id" label="Asset lending agreement template" :options="$templateOptions" :value="(string) ($r?->asset_lending_agreement_template_id ?? '')" />
</x-admin.step>
