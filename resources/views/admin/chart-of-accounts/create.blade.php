<x-admin.create-page
    title="New GL account" heading="New GL account" subheading="Add to chart of accounts"
    :action="route('admin.chart-of-accounts.store')" :cancelUrl="route('admin.chart-of-accounts.index')"
    submitLabel="Create account">
    @include('admin.chart-of-accounts._form', ['record' => null])
</x-admin.create-page>
