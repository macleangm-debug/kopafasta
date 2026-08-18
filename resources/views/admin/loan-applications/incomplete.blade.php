<x-admin.layout
    :title="__('admin.application_drafts.title')"
    heading=""
    subheading="">
    <x-admin.letterhead
        kicker="Applications"
        :title="__('admin.application_drafts.title')"
        :subtitle="__('admin.application_drafts.subtitle')" />

    <div class="mb-4 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
        {{ __('admin.application_drafts.hint') }}
    </div>

    @livewire('admin.loan-application-drafts-table')
</x-admin.layout>
