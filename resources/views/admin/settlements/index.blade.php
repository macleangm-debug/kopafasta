<x-admin.layout title="Payment gateway settlements" heading="Payment gateway settlements" subheading="Mpesa, TigoPesa, Airtel, and bank settlement batches — for reconciling collected transactions, not partner payouts">
    <x-admin.index-toolbar route="admin.settlements" label="New settlement" />
    @livewire('admin.settlements-table')
</x-admin.layout>
