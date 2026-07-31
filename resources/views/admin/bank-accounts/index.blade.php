<x-admin.layout title="Bank Accounts" heading="Bank Accounts" subheading="Operating, disbursement, and collection bank accounts">
    @include('admin.settings._tabs', ['active' => 'bank-accounts'])
    <div class="mb-4 rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700">
        Map which bank account collects each payment type under
        <a href="{{ route('admin.settings.payment-accounts') }}" class="font-semibold text-brand hover:underline">Settings → Payment accounts</a>.
    </div>
    <x-admin.index-toolbar route="admin.bank-accounts" label="New bank account" />
    @livewire('admin.bank-accounts-table')
</x-admin.layout>
