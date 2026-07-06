<x-admin.edit-page
    title="Edit profile section"
    heading="Edit profile section"
    :action="route('admin.profile-sections.update', $record)"
    :cancelUrl="route('admin.profile-sections.index')"
    submitLabel="Save section">
    @include('admin.profile-sections._form', ['record' => $record])
</x-admin.edit-page>
