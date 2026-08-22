<x-admin.layout
    title="Awaiting activation"
    heading=""
    subheading="">
    <x-admin.letterhead
        kicker="Partners hub"
        title="Awaiting activation"
        subtitle="Approved partners land here until they set a PIN. Enrollment applications stay in the screening queue until you approve." />
    <div class="mb-4 flex flex-wrap gap-3 text-sm">
        <a href="{{ route('admin.partners.index') }}" class="font-semibold text-brand hover:underline">← Partners hub</a>
        <a href="{{ route('admin.partner-applications.index') }}" class="font-semibold text-brand hover:underline">Applications to screen →</a>
    </div>
    @livewire('admin.partners-table', ['status' => 'inactive', 'lockStatus' => true, 'reviewOnly' => true])
</x-admin.layout>
