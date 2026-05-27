<x-admin.create-page
    title="New expense"
    heading="New expense"
    subheading="Log an operational cost"
    :action="route('admin.expenses.store')"
    :cancelUrl="route('admin.expenses.index')"
    submitLabel="Create expense">
    @include('admin.expenses._form', ['record' => null])
</x-admin.create-page>
