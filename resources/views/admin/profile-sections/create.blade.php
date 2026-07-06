<x-admin.create-page
    title="New profile section"
    heading="New profile section"
    subheading="Define a configurable profile section"
    :action="route('admin.profile-sections.store')"
    :cancelUrl="route('admin.profile-sections.index')"
    submitLabel="Create section">
    @include('admin.profile-sections._form', ['record' => null])
</x-admin.create-page>
