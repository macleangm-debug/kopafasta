<x-admin.edit-page
    :title="'Edit '.$record->name"
    heading="Edit partner"
    :subheading="$record->vendor_number"
    :action="route('admin.partners.update', $record)"
    :destroyAction="route('admin.partners.destroy', $record)"
    :deactivateAction="route('admin.partners.deactivate', $record)"
    :cancelUrl="route('admin.partners.show', $record)"
    submitLabel="Save changes"
    deleteTitle="Delete this partner?"
    deleteLabel="Delete"
    deleteHint="Delete permanently removes empty partners. Deactivate keeps history and disables portal login."
    deleteConfirm="This permanently deletes the partner. Open or completed jobs cannot be deleted with them — halt open tasks, then Deactivate."
    deactivateTitle="Deactivate this partner?"
    deactivateLabel="Deactivate"
    deactivateConfirm="Open jobs are halted and offered to another partner. This partner is suspended, login is disabled, and history is kept."
    enctype="multipart/form-data">
    @include('admin.partners._form')
</x-admin.edit-page>
