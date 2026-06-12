@props(['customer', 'wizardMode' => false, 'wizardKey' => 'nida'])

@if ($wizardMode)
    @php
        $nav = app(\App\Services\ProfileWizardService::class)->navigation($customer, $wizardKey);
    @endphp
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4">
        @if ($nav['previous'])
            <a href="{{ $nav['previous']['url'] }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">← {{ $nav['previous']['label'] }}</a>
        @else
            <span></span>
        @endif
        @if ($nav['next'] && $nav['next']['key'] !== $wizardKey)
            <a href="{{ $nav['next']['url'] }}" class="text-sm font-semibold text-amber-700 hover:underline">{{ __('borrower.profile_wizard.skip_to', ['step' => $nav['next']['label']]) }} →</a>
        @elseif (app(\App\Services\ProfileWizardService::class)->isComplete($customer))
            <a href="{{ route('site.borrower.dashboard') }}" class="inline-flex bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.profile_wizard.finish') }}</a>
        @endif
    </div>
@endif
