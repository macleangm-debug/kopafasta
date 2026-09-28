<x-admin.layout title="Loan Applications" heading="" subheading="">
    <x-admin.letterhead kicker="Lending" title="Loan applications" :subtitle="__('admin.intake.subtitle')" />
    <x-admin.index-toolbar route="admin.loan-applications" label="New application" />
    @include('admin.loan-applications._pipeline-tabs', ['active' => 'intake'])

    @php
        $counts = app(\App\Services\ApplicationIntakeReadinessService::class)->intakeCounts();
        $section = request('section', 'initial_check');
        $sections = [
            'initial_check' => [__('admin.intake.initial_check'), $counts['initial_check'] ?? 0],
            'awaiting_guarantor' => [__('admin.intake.waiting_guarantor'), $counts['awaiting_guarantor'] ?? 0],
            'ready_for_screening' => [__('admin.intake.ready'), $counts['ready_for_screening'] ?? 0],
            'drafts' => [__('admin.intake.drafts'), $counts['drafts'] ?? 0],
            'closed' => [__('admin.intake.closed'), $counts['closed'] ?? 0],
        ];
    @endphp

    <nav class="flex flex-wrap gap-2 mb-4" aria-label="Intake sections">
        @foreach ($sections as $key => [$label, $count])
            <a href="{{ route('admin.loan-applications.index', ['section' => $key]) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $section === $key ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
                <span class="ml-1 tabular-nums text-xs {{ $section === $key ? 'text-brand/80' : 'text-gray-400' }}">{{ $count }}</span>
            </a>
        @endforeach
    </nav>

    @if ($section === 'drafts')
        @livewire('admin.loan-application-drafts-table')
    @else
        @livewire('admin.loan-applications-table', ['pipeline' => 'intake', 'intakeSection' => $section])
    @endif
</x-admin.layout>
