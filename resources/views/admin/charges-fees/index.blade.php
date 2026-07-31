<x-admin.layout title="Fee management" heading="Fee management" subheading="Central catalog: fixed or % fees by type and when they are charged. Membership fee lives under Settings → Membership.">
    @include('admin.settings._tabs', ['active' => 'fees'])
    <div class="mb-4 rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700 space-y-1">
        <p><strong>When = post_approval</strong> means after the loan is approved and before disbursement (GPS, insurance %, asset registration, etc.).</p>
        <p><strong>REG_POST_FEE</strong> is asset registration/transfer — not membership. Membership registration is Settings → Membership.</p>
        <p>Fixed vs percentage is the <em>Basis</em> field. Loan products select which fees apply.</p>
    </div>
    <x-admin.index-toolbar route="admin.charges-fees" label="New fee" />
    @livewire('admin.charges-fees-table')
</x-admin.layout>
