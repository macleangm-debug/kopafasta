<x-admin.create-page
    title="New PEP flag" heading="New PEP flag" subheading="Flag a politically exposed person"
    :action="route('admin.pep-flags.store')" :cancelUrl="route('admin.pep-flags.index')"
    submitLabel="Add flag">
    @include('admin.pep-flags._form', ['record' => null])
</x-admin.create-page>
