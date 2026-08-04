<x-admin.edit-page
    :title="'Edit expense '.$record->reference"
    heading="Edit operational expense"
    :subheading="app(\App\Services\OperationalExpenseCategoryService::class)->labelFor((string) ($record->category ?? ''))"
    :action="route('admin.expenses.update', $record)"
    :destroyAction="route('admin.expenses.destroy', $record)"
    :cancelUrl="route('admin.expenses.show', $record)"
    submitLabel="Save changes">
    @include('admin.expenses._form')
</x-admin.edit-page>
