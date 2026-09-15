@php
    $form = compact(
        'customers',
        'agents',
        'agentCount',
        'priorities',
        'statuses',
        'categories',
        'subjectsByCategory',
        'sources',
        'contactKinds',
    );
@endphp
<x-admin.create-page
    title="New ticket"
    heading="New support ticket"
    subheading="Customer or Guest — searchable intake, guided category, auto-assign"
    :action="route('admin.support-tickets.store')"
    :cancelUrl="route('admin.support-tickets.index')"
    submitLabel="Create ticket">
    @include('admin.support-tickets._form', ['record' => null] + $form)
</x-admin.create-page>
