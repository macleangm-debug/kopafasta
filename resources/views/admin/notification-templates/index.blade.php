<x-admin.layout title="Notification Templates" heading="Templates" subheading="Edit EN/SW copy here. Channel switches stay in Settings → Messaging.">
    @can('settings.manage')
        @include('admin.settings._tabs', ['active' => 'notification-templates'])
    @endcan
    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-sm text-gray-600">Edit what borrowers receive. Turn channels on/off under <a href="{{ route('admin.settings.messaging') }}" class="font-semibold text-brand hover:underline">Transactional messaging</a>.</p>
        <a href="{{ route('admin.notification-templates.create') }}"
           class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand text-sm font-semibold px-4 py-2.5 hover:brightness-95 shrink-0">
            + New template
        </a>
    </div>
    @livewire('admin.notification-templates-table')
</x-admin.layout>
