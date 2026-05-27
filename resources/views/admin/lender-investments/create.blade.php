<x-admin.create-page
    title="New investment"
    heading="New investment"
    subheading="Record a lender placement"
    :action="route('admin.lender-investments.store')"
    :cancelUrl="route('admin.lender-investments.index')"
    submitLabel="Create investment">
    @include('admin.lender-investments._form', ['record' => null])
</x-admin.create-page>
