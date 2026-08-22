<x-admin.create-page
    title="New user"
    heading="New user"
    subheading="Person, desk, then access — one section at a time"
    :action="route('admin.users.store')"
    :cancelUrl="route('admin.users.index')"
    submitLabel="Create user">
    @include('admin.users._form', ['record' => null])
</x-admin.create-page>
