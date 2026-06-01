<x-admin.edit-page
    :title="'Edit approval limit'"
    heading="Edit approval limit"
    :subheading="display_label($record->role_code, 'role').' · '.display_label($record->action, 'approval_action')"
    :action="route('admin.approval-limits.update', $record)"
    :destroyAction="route('admin.approval-limits.destroy', $record)"
    :cancelUrl="route('admin.approval-limits.show', $record)"
    submitLabel="Save changes">
    @include('admin.approval-limits._form')
</x-admin.edit-page>
