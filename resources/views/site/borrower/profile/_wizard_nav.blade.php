@props(['customer', 'currentKey', 'wizardMode' => false])

@if ($wizardMode)
    @php
        $nav = app(\App\Services\ProfileWizardService::class)->navigation($customer, $currentKey);
        $progress = $nav['progress'];
    @endphp
    <div class="mb-6 rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div>
                <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">{{ __('borrower.profile_wizard.title') }}</p>
                <p class="text-sm text-gray-600 mt-0.5">{{ __('borrower.profile_wizard.step_of', ['current' => $nav['index'] + 1, 'total' => $nav['total']]) }} — {{ $nav['current']['label'] ?? '' }}</p>
            </div>
            <span class="text-sm font-semibold text-indigo-700">{{ __('borrower.kyc_progress.percent_complete', ['percent' => $progress['percent']]) }}</span>
        </div>
        <div class="h-2 rounded-full bg-indigo-100 overflow-hidden mb-4">
            <div class="h-full bg-indigo-500 rounded-full transition-all" style="width: {{ min(100, max(0, (int) $progress['percent'])) }}%"></div>
        </div>
        <ol class="flex flex-wrap gap-2 text-xs">
            @foreach ($progress['steps'] as $index => $step)
                <li>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 ring-1
                        {{ $step['complete'] ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : ($step['key'] === $currentKey ? 'bg-indigo-100 text-indigo-900 ring-indigo-300 font-semibold' : 'bg-white text-gray-600 ring-gray-200') }}">
                        <span>{{ $step['complete'] ? '✓' : ($index + 1) }}</span>
                        <span>{{ $step['label'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
        @if ($nav['next'] && $nav['next']['key'] !== $currentKey)
            <p class="text-xs text-gray-500 mt-3">{{ __('borrower.profile_wizard.up_next') }}: <span class="font-semibold text-indigo-700">{{ $nav['next']['label'] }}</span></p>
        @endif
    </div>
@endif
