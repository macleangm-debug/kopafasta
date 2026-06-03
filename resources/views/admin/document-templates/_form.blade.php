@php($r = $record ?? null)
<x-admin.step title="Document template">
    <x-admin.input  name="code" label="Code" :value="$r?->code" required placeholder="loan_agreement, guarantor_form…" />
    <x-admin.input  name="name" label="Name" :value="$r?->name" required />
    <x-admin.select name="is_active" label="Status" :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
    <div class="md:col-span-2">
        @php($templateHelp = 'Use @{{ $variable }} placeholders when generating PDFs (customer name, principal, monthly rate, repayment schedule, signatures, etc.). Test with a real application before going live.')
        <x-admin.textarea name="content" label="Content (Blade / HTML)" :value="$r?->content" rows="14" required
                          :help="$templateHelp" />
    </div>
</x-admin.step>
