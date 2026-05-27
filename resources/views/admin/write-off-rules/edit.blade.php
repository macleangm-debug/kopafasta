<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit write-off rule" :subheading="''.$record->days_past_due.' DPD'"
    :action="route('admin.write-off-rules.update', $record)"
    :destroyAction="route('admin.write-off-rules.destroy', $record)"
    :cancelUrl="route('admin.write-off-rules.show', $record)"
    submitLabel="Save changes">
    @include('admin.write-off-rules._form')
</x-admin.edit-page>
