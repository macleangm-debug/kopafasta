<x-admin.layout title="Partner Applications" heading="Partner Applications" subheading="Partners awaiting onboarding approval">
    <div class="mb-4 flex flex-wrap gap-3 text-sm">
        <a href="{{ route('admin.partners.index') }}" class="font-semibold text-brand hover:underline">← Partners hub</a>
        <a href="{{ route('admin.partner-applications.index') }}" class="font-semibold text-brand hover:underline">Enrollment applications →</a>
    </div>
    @livewire('admin.partners-table', ['status' => 'inactive', 'lockStatus' => true])
</x-admin.layout>
