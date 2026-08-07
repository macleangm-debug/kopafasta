@props([
    'level' => null,
    'partnerName' => null,
    'slaDueAt' => null,
    'slaDaysLeft' => null,
    'status' => null,
    'assignmentId' => null,
    'arrearCaseId' => null,
    'breached' => false,
])

@if ($level || $partnerName)
    <div class="mt-4 rounded-xl ring-1 {{ $breached ? 'ring-red-200 bg-red-50' : 'ring-amber-200 bg-amber-50' }} p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest font-semibold {{ $breached ? 'text-red-700' : 'text-amber-800' }}">
                    Recovery partner automation
                </p>
                <p class="mt-1 text-sm font-bold {{ $breached ? 'text-red-900' : 'text-amber-950' }}">
                    {{ $level ?: 'Recovery in progress' }}
                    @if ($partnerName)
                        <span class="font-semibold">· {{ $partnerName }}</span>
                    @endif
                </p>
                <p class="mt-1 text-xs {{ $breached ? 'text-red-800' : 'text-amber-900/80' }}">
                    @if ($breached)
                        SLA breached{{ $slaDueAt ? ' on '.$slaDueAt->format('d M Y') : '' }}. Escalate or update the partner case.
                    @elseif ($slaDaysLeft !== null)
                        SLA left: <strong>{{ $slaDaysLeft }} day{{ $slaDaysLeft === 1 ? '' : 's' }}</strong>
                        @if ($slaDueAt)
                            (due {{ $slaDueAt->format('d M Y') }})
                        @endif
                    @elseif ($slaDueAt)
                        SLA due {{ $slaDueAt->format('d M Y') }}
                    @else
                        Open recovery assignment{{ $status ? ' · '.ucfirst(str_replace('_', ' ', $status)) : '' }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($assignmentId)
                    <a href="{{ route('admin.recovery.assignments.show', $assignmentId) }}"
                       class="inline-flex text-xs font-semibold px-3 py-1.5 rounded-lg bg-white ring-1 ring-amber-200 text-amber-900 hover:bg-amber-100">
                        View assignment
                    </a>
                @endif
                @if ($arrearCaseId)
                    <a href="{{ route('admin.arrear-cases.show', $arrearCaseId) }}"
                       class="inline-flex text-xs font-semibold px-3 py-1.5 rounded-lg bg-white ring-1 ring-amber-200 text-amber-900 hover:bg-amber-100">
                        Collection case
                    </a>
                @endif
            </div>
        </div>
    </div>
@endif
