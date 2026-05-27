<x-admin.create-page
    title="New user"
    heading="New user"
    subheading="Create an admin or staff account"
    :action="route('admin.users.store')"
    :cancelUrl="route('admin.users.index')"
    submitLabel="Create user">
    @include('admin.users._form', ['record' => null])
</x-admin.create-page>
