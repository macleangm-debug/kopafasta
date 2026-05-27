<x-admin.create-page
    title="New guarantor"
    heading="New guarantor"
    subheading="Register a loan guarantor"
    :action="route('admin.guarantors.store')"
    :cancelUrl="route('admin.guarantors.index')"
    submitLabel="Create guarantor">
    @include('admin.guarantors._form', ['record' => null])
</x-admin.create-page>
