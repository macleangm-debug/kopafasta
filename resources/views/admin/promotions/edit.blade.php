<x-admin.edit-page
    title="Edit campaign"
    heading="{{ $record->name }}"
    :action="route('admin.promotions.update', $record)"
    :destroyAction="route('admin.promotions.destroy', $record)"
    :cancelUrl="route('admin.promotions.show', $record)"
    submitLabel="Save campaign">
    @include('admin.promotions._form')
</x-admin.edit-page>
