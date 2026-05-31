@php
    $currentStage = $record->current_stage ?? 'submitted';
    $stages = ['submitted', 'screening', 'credit_appraisal', 'pre_approval', 'approval', 'disbursement'];
    $currentIndex = array_search($currentStage, $stages, true);
    if ($currentStage === 'rejected') {
        $currentIndex = false;
    }
@endphp

<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Application workflow</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                Current stage:
                <span class="font-semibold text-gray-800">{{ $workflow->stageLabel($currentStage) }}</span>
            </p>
        </div>
        @if ($currentStage === 'rejected')
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-red-100 text-red-800">Rejected</span>
        @elseif ($currentStage === 'disbursement')
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-emerald-100 text-emerald-800">Ready for disbursement</span>
        @endif
    </div>

    @if ($currentStage !== 'rejected')
        <ol class="flex flex-wrap gap-1 mb-6">
            @foreach ($stages as $index => $stage)
                @php
                    $done = $currentIndex !== false && $index < $currentIndex;
                    $active = $stage === $currentStage;
                @endphp
                <li class="flex items-center gap-1">
                    <span @class([
                        'text-[10px] font-semibold rounded-full px-2.5 py-1 whitespace-nowrap',
                        'bg-emerald-100 text-emerald-800' => $done,
                        'bg-amber-100 text-amber-900 ring-2 ring-amber-300' => $active,
                        'bg-gray-100 text-gray-500' => ! $done && ! $active,
                    ])>{{ $workflow->stageLabel($stage) }}</span>
                    @if (! $loop->last)
                        <span class="text-gray-300 hidden sm:inline">→</span>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    @if ($availableActions->isNotEmpty())
        <div class="border-t border-gray-100 pt-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Available actions</p>
            <div class="flex flex-wrap gap-3">
                @foreach ($availableActions as $action)
                    @if ($action['key'] === 'reject')
                        <div x-data="{ open: false }">
                            <button type="button" @click="open = true"
                                    class="inline-flex items-center gap-2 text-sm font-semibold text-red-800 bg-red-100 hover:bg-red-200 px-4 py-2.5 rounded-lg">
                                {{ $action['label'] }}
                            </button>
                            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
                                <div class="relative bg-white rounded-xl shadow-xl ring-1 ring-gray-200 w-full max-w-md p-6">
                                    <h4 class="font-semibold text-gray-900">Reject application</h4>
                                    <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}" class="mt-4 space-y-3">
                                        @csrf
                                        <input type="hidden" name="action" value="reject">
                                        <textarea name="remarks" required rows="3" maxlength="1000" placeholder="Reason for rejection (shown to borrower)"
                                                  class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                                            <button class="bg-red-600 hover:bg-red-700 text-white font-semibold text-sm px-4 py-2 rounded-lg">Confirm reject</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.loan-applications.workflow', $record) }}">
                            @csrf
                            <input type="hidden" name="action" value="{{ $action['key'] }}">
                            <button class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-4 py-2.5 rounded-lg">
                                {{ $action['label'] }}
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
    @elseif ($currentStage !== 'rejected' && $currentStage !== 'disbursement')
        <p class="text-sm text-gray-500 border-t border-gray-100 pt-4">No workflow actions available for your role at this stage.</p>
    @endif
</div>

<div class="mt-6 grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Stage history</h3>
        @if ($stageHistory->isEmpty())
            <p class="text-sm text-gray-500">No stage changes recorded yet.</p>
        @else
            <ul class="space-y-4">
                @foreach ($stageHistory as $entry)
                    <li class="flex gap-3">
                        <div class="mt-1 size-2 rounded-full bg-amber-500 shrink-0"></div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $workflow->stageLabel($entry->from_stage ?? 'start') }}
                                →
                                {{ $workflow->stageLabel($entry->to_stage) }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $entry->created_at?->format('d M Y, H:i') }}
                                @if ($entry->changedByUser)
                                    · {{ $entry->changedByUser->name }}
                                @endif
                            </p>
                            @if ($entry->remarks)
                                <p class="text-xs text-gray-600 mt-1 bg-gray-50 rounded-lg px-3 py-2">{{ $entry->remarks }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Audit trail</h3>
        @if ($auditLogs->isEmpty())
            <p class="text-sm text-gray-500">No audit entries for this application yet.</p>
        @else
            <ul class="space-y-3">
                @foreach ($auditLogs as $log)
                    <li class="text-sm border-b border-gray-50 pb-3 last:border-0">
                        <p class="font-medium text-gray-800">{{ str_replace('.', ' · ', $log->event) }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $log->created_at?->format('d M Y, H:i') }}
                            @if ($log->user) · {{ $log->user->name }} @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
