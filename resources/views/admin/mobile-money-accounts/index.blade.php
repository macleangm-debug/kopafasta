<x-admin.layout title="Mobile Money (PSP)" heading="Mobile Money (PSP)" subheading="One aggregation collection account for the payment gateway — plus optional disbursement wallet">
    @include('admin.settings._tabs', ['active' => 'mobile-money'])
    <div class="mb-4 rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700">
        Create a <strong>collection</strong> account (paybill/till) for the PSP aggregator, then set it as the default under
        <a href="{{ route('admin.settings.payment-accounts') }}" class="font-semibold text-brand hover:underline">Settings → Payment accounts</a>.
        You do not need one account per mobile network for collections.
    </div>
    <x-admin.index-toolbar route="admin.mobile-money-accounts" label="New PSP account" />
    @livewire('admin.mobile-money-accounts-table')
</x-admin.layout>
