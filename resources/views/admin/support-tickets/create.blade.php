@php
    $form = compact(
        'customers',
        'agents',
        'agentCount',
        'canOverrideAssignment',
        'priorities',
        'statuses',
        'categories',
        'subjectsByCategory',
        'sources',
        'contactKinds',
        'ticketNumberPreview',
    );
@endphp
<x-admin.create-page
    title="New ticket"
    heading="New support ticket"
    subheading="Member or Guest — searchable intake, guided category, auto-assign"
    :action="route('admin.support-tickets.store')"
    :cancelUrl="route('admin.support-tickets.index')"
    submitLabel="Create ticket">
    @include('admin.support-tickets._form', ['record' => null] + $form)
</x-admin.create-page>
