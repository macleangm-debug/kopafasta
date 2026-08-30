@php
    $next = $walk['next'] ?? [];
    $condition = $walk['condition'] ?? null;
    $waiting = ! empty($walk['waiting']);
    $ready = ! empty($walk['ready']);
    $contract = $walk['contract_readiness'] ?? [];
    $isContractStep = ($condition['key'] ?? '') === 'contract_executed';
@endphp
<x-admin.layout
    :title="$record->application_number.' · Post-Approval'"
    heading=""
    :backUrl="route('admin.loan-applications.show', $record)"
    backLabel="Credit file">

    <div class="max-w-xl mx-auto pb-28">
        <p class="text-[11px] font-bold uppercase tracking-widest text-brand">Post-approval</p>
        <h1 class="text-xl font-bold text-slate-900 mt-1 break-words">
            @if ($ready)
                Ready for disbursement
            @elseif ($waiting)
                Waiting · {{ $condition['label'] ?? 'condition' }}
            @else
                Step {{ $condition['index'] ?? '' }} · {{ $condition['label'] ?? 'Conditions to disbursement' }}
            @endif
        </h1>
        <p class="text-sm text-slate-600 mt-1">{{ $record->application_number }} · Committee already approved. Do not re-underwrite.</p>

        <div class="mt-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">What happens next</p>
            <p class="text-sm text-slate-800 mt-1">{{ $next['what_happens_next'] ?? '' }}</p>
            @if (! empty($condition['party_label']))
                <p class="text-xs text-slate-600 mt-2">Responsible: {{ $condition['party_label'] }}
                    @if (! empty($condition['timing_label']))
                        · {{ $condition['timing_label'] }}
                    @endif
                </p>
            @endif
        </div>

        @if ($isContractStep)
            <div @class([
                'mt-4 rounded-2xl px-4 py-4 ring-1',
                'bg-emerald-50 ring-emerald-200' => ! empty($walk['contract_ready']),
                'bg-amber-50 ring-amber-200' => empty($walk['contract_ready']),
            ])>
                <p class="text-sm font-bold">{{ $contract['headline'] ?? '' }}</p>
                <p class="text-sm mt-1">{{ $contract['detail'] ?? '' }}</p>
                @if (! empty($walk['contract_ready']))
                    <form method="POST" action="{{ route('admin.loan-applications.contract', $record) }}"
                          class="mt-3 space-y-2" x-data="{ step: 'review' }" data-no-draft>
                        @csrf
                        <button type="button" x-show="step === 'review'" @click="step = 'confirm'"
                                class="w-full rounded-xl bg-brand text-white font-bold text-sm py-3">Generate agreement</button>
                        <div x-show="step === 'confirm'" x-cloak class="space-y-2">
                            <p class="text-sm font-semibold">Generate the loan agreement for {{ $record->application_number }}? Signing it will not activate the loan.</p>
                            <div class="flex gap-2">
                                <button type="button" @click="step = 'review'" class="flex-1 rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3">Go back</button>
                                <button type="submit" class="flex-1 rounded-xl bg-brand text-white font-bold text-sm py-3">Generate agreement</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        <div class="mt-6 rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-4 space-y-2">
            @foreach ($walk['conditions'] ?? [] as $row)
                @if (empty($row['applies']))
                    @continue
                @endif
                <div class="flex justify-between gap-2 text-sm">
                    <span class="break-words min-w-0">{{ $row['label'] ?? '' }}</span>
                    <span class="font-bold shrink-0 {{ ! empty($row['complete']) ? 'text-emerald-800' : 'text-amber-800' }}">
                        {{ ! empty($row['complete']) ? 'Done' : ($row['waiting'] ? 'Waiting' : 'Open') }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 backdrop-blur px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <div class="max-w-xl mx-auto flex gap-2 items-stretch">
            <a href="{{ route('admin.loan-applications.show', $record) }}"
               class="flex-1 min-w-0 text-center rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Back</a>
            @if ($ready)
                <a href="{{ $walk['desk_href'] }}"
                   class="flex-[2] min-w-0 text-center rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Review disbursement</a>
            @elseif ($waiting)
                <span class="flex-[2] min-w-0 text-center rounded-xl bg-amber-100 text-amber-950 font-bold text-sm py-3 px-2 leading-snug whitespace-normal">{{ $next['cta'] ?? 'Waiting' }}</span>
            @elseif ($isContractStep && empty($walk['contract_ready']))
                <span class="flex-[2] min-w-0 text-center rounded-xl bg-slate-100 text-slate-500 font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Confirm in the card</span>
            @elseif ($isContractStep)
                <span class="flex-[2] min-w-0 text-center rounded-xl bg-slate-100 text-slate-500 font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Confirm in the card</span>
            @else
                <a href="{{ $walk['desk_href'] }}"
                   class="flex-[2] min-w-0 text-center rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug whitespace-normal">{{ $next['cta'] ?? 'Continue Post-Approval' }}</a>
            @endif
        </div>
    </div>
</x-admin.layout>
