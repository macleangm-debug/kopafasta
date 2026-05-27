<x-admin.edit-page
    :title="'Edit '.$record->role_code.' / '.$record->action" heading="Edit approval limit" :subheading="$record->role_code"
    :action="route('admin.approval-limits.update', $record)"
    :destroyAction="route('admin.approval-limits.destroy', $record)"
    :cancelUrl="route('admin.approval-limits.show', $record)"
    submitLabel="Save changes">
    @include('admin.approval-limits._form')
</x-admin.edit-page>
