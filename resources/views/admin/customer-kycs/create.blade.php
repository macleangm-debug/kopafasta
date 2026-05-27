<x-admin.create-page
    title="New KYC record"
    heading="New KYC record"
    subheading="Capture customer verification"
    :action="route('admin.customer-kycs.store')"
    :cancelUrl="route('admin.customer-kycs.index')"
    submitLabel="Create KYC">
    @include('admin.customer-kycs._form', ['record' => null])
</x-admin.create-page>
