<x-admin.create-page
    title="New expense"
    heading="New expense"
    subheading="Log a manual operating cost (not partner payouts)"
    :action="route('admin.expenses.store')"
    :cancelUrl="route('admin.expenses.index')"
    submitLabel="Create expense">
    @include('admin.expenses._form', ['record' => null])
</x-admin.create-page>
