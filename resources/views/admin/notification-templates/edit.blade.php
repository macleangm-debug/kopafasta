<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit notification template" :subheading="$record->code"
    :action="route('admin.notification-templates.update', $record)"
    :destroyAction="route('admin.notification-templates.destroy', $record)"
    :cancelUrl="route('admin.notification-templates.show', $record)"
    submitLabel="Save changes">
    @include('admin.notification-templates._form')
</x-admin.edit-page>
