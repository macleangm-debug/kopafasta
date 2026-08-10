<x-admin.edit-page
    :title="'Edit '.$record->name"
    heading="Edit partner"
    :subheading="$record->vendor_number"
    :action="route('admin.partners.update', $record)"
    :destroyAction="route('admin.partners.destroy', $record)"
    :cancelUrl="route('admin.partners.show', $record)"
    submitLabel="Save changes"
    deleteConfirm="Remove this partner? Empty partners are deleted permanently. Partners with history are deactivated and portal login is disabled."
    enctype="multipart/form-data">
    @include('admin.partners._form')
</x-admin.edit-page>
