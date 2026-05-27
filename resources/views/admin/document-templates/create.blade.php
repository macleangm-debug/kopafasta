<x-admin.create-page
    title="New document template" heading="New document template" subheading="Contract / form template"
    :action="route('admin.document-templates.store')" :cancelUrl="route('admin.document-templates.index')"
    submitLabel="Create template">
    @include('admin.document-templates._form', ['record' => null])
</x-admin.create-page>
