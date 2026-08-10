<x-admin.edit-page
    :title="'Edit '.$record->name"
    heading="Edit partner"
    :subheading="$record->vendor_number"
    :action="route('admin.partners.update', $record)"
    :destroyAction="route('admin.partners.destroy', $record)"
    :cancelUrl="route('admin.partners.show', $record)"
    submitLabel="Save changes"
    deleteTitle="Remove this partner?"
    deleteLabel="Delete / deactivate"
    deleteHint="Empty partners are deleted permanently. Partners with history are deactivated (history kept, portal login disabled)."
    deleteConfirm="Empty partners are deleted permanently. Partners with tasks, payments, or assignments are deactivated instead."
    enctype="multipart/form-data">
    @include('admin.partners._form')
</x-admin.edit-page>
