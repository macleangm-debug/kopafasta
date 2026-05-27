<x-admin.create-page
    title="New mobile money account" heading="New mobile money account" subheading="MNO float account"
    :action="route('admin.mobile-money-accounts.store')" :cancelUrl="route('admin.mobile-money-accounts.index')"
    submitLabel="Create account">
    @include('admin.mobile-money-accounts._form', ['record' => null])
</x-admin.create-page>
