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
    deleteConfirm="This permanently deletes the partner. Partners with tasks, payments, or assignments cannot be deleted — use Deactivate instead."
    deactivateTitle="Deactivate this partner?"
    deactivateLabel="Deactivate"
    deactivateConfirm="The partner will be suspended and portal login disabled. History is kept."
    enctype="multipart/form-data">
    @include('admin.partners._form')
</x-admin.edit-page>
