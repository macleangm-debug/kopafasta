<x-admin.layout title="Notification Templates" heading="Notification Templates" subheading="SMS, email, push & in-app messages">
    @include('admin.settings._tabs', ['active' => 'notification-templates'])
    <x-admin.index-toolbar route="admin.notification-templates" label="New template" />
    @livewire('admin.notification-templates-table')
</x-admin.layout>
