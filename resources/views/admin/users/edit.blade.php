<x-admin.edit-page
    :title="'Edit '.$record->name"
    heading="Edit user"
    subheading="Personal · Capabilities · Work / Team · Security — switch sections freely"
    :action="route('admin.users.update', $record)"
    :destroyAction="route('admin.users.destroy', $record)"
    :cancelUrl="route('admin.users.show', $record)"
    submitLabel="Save changes">
    @include('admin.users._form')
</x-admin.edit-page>
