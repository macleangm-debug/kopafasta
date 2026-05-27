<x-admin.edit-page
    :title="'Edit STR '.$record->id" heading="Edit suspicious activity" :subheading="$record->activity_type"
    :action="route('admin.suspicious-activities.update', $record)"
    :destroyAction="route('admin.suspicious-activities.destroy', $record)"
    :cancelUrl="route('admin.suspicious-activities.show', $record)"
    submitLabel="Save changes">
    @include('admin.suspicious-activities._form')
</x-admin.edit-page>
