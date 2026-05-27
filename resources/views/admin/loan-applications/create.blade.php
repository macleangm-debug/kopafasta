<x-admin.create-page
    title="New application"
    heading="New loan application"
    subheading="Start the credit journey"
    :action="route('admin.loan-applications.store')"
    :cancelUrl="route('admin.loan-applications.index')"
    submitLabel="Create application">
    @include('admin.loan-applications._form', ['record' => null])
</x-admin.create-page>
