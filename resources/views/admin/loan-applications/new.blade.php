<x-admin.layout title="New Applications" heading="" subheading="">
    <x-admin.letterhead kicker="Applications" title="New applications" subtitle="Freshly submitted applications" />
<div class="flex items-center justify-end mb-4">
        <a href="{{ route('admin.loan-applications.create') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-lg shadow-sm transition">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            New application
        </a>
    </div>

    @livewire('admin.loan-applications-table', ['stage' => 'submitted', 'lockStage' => true])
</x-admin.layout>
