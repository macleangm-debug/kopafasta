<x-admin.create-page
    title="New AML rule" heading="New AML rule" subheading="Transaction monitoring rule"
    :action="route('admin.aml-rules.store')" :cancelUrl="route('admin.aml-rules.index')"
    submitLabel="Create rule">
    @include('admin.aml-rules._form', ['record' => null])
</x-admin.create-page>
