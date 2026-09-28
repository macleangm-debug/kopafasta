<x-admin.layout title="System sorted" heading="" subheading="">
    <x-admin.letterhead kicker="Credit screening" title="{{ __('admin.intake.system_sorted_title') }}" subtitle="{{ __('admin.intake.system_sorted_subtitle') }}" />
@include('admin.loan-applications._pipeline-tabs', ['active' => 'system_sorted'])

    @php
        $counts = app(\App\Services\ApplicationIntakeReadinessService::class)->systemSortedCounts();
        $section = request('section');
        $sections = [
            '' => [__('admin.intake.system_sorted_all'), $counts['total'] ?? 0],
            'parked' => [__('admin.intake.system_sorted_parked'), $counts['parked'] ?? 0],
            'ready_for_screening' => [__('admin.intake.ready'), $counts['ready_for_screening'] ?? 0],
            'awaiting_guarantor' => [__('admin.intake.waiting_guarantor'), $counts['awaiting_guarantor'] ?? 0],
        ];
    @endphp

    <nav class="flex flex-wrap gap-2 mb-4" aria-label="{{ __('admin.intake.system_sorted_title') }}">
        @foreach ($sections as $key => [$label, $count])
            <a href="{{ route('admin.loan-applications.pipeline.system-sorted', $key !== '' ? ['section' => $key] : []) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ (string) $section === (string) $key ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
                <span class="ml-1 tabular-nums text-xs {{ (string) $section === (string) $key ? 'text-brand/80' : 'text-gray-400' }}">{{ $count }}</span>
            </a>
        @endforeach
    </nav>

    <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
        {{ __('admin.intake.system_sorted_hint') }}
        <a href="{{ route('admin.loan-applications.pipeline.under-review') }}" class="font-semibold underline">{{ __('admin.intake.active_credit_screening') }}</a>.
    </div>

    @livewire('admin.loan-applications-table', ['pipeline' => 'system_sorted', 'lockStage' => true, 'hideSystemSorted' => false, 'intakeSection' => $section ?: null])
</x-admin.layout>
