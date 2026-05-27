<x-admin.edit-page
    :title="'Edit settlement '.$record->reference"
    heading="Edit settlement"
    :subheading="$record->reference"
    :action="route('admin.settlements.update', $record)"
    :destroyAction="route('admin.settlements.destroy', $record)"
    :cancelUrl="route('admin.settlements.show', $record)"
    submitLabel="Save changes">
    @include('admin.settlements._form')
</x-admin.edit-page>
