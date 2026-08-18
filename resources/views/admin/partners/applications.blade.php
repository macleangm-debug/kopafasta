<x-admin.layout
    title="Pending partner onboarding"
    heading=""
    subheading="">
    <x-admin.letterhead
        kicker="Partners"
        title="Pending partner onboarding"
        subtitle="Inactive partners awaiting activation. Enrollment applications from the public form are reviewed separately." />
    <div class="mb-4 flex flex-wrap gap-3 text-sm">
        <a href="{{ route('admin.partners.index') }}" class="font-semibold text-brand hover:underline">← Partners hub</a>
        <a href="{{ route('admin.partner-applications.index') }}" class="font-semibold text-brand hover:underline">Enrollment applications →</a>
    </div>
    @livewire('admin.partners-table', ['status' => 'inactive', 'lockStatus' => true, 'reviewOnly' => true])
</x-admin.layout>
