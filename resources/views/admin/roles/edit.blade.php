<x-admin.edit-page
    :title="'Edit '.$record->name"
    heading="Edit role"
    :subheading="$record->name"
    :action="route('admin.roles.update', $record)"
    :destroyAction="route('admin.roles.destroy', $record)"
    :cancelUrl="route('admin.roles.index')"
    backLabel="Back to roles"
    submitLabel="Save changes">
    @include('admin.roles._form', ['record' => $record])
</x-admin.edit-page>
