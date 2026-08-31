@props([
    'record',
    'mode' => 'screening',
    'percent' => 0,
    'gateChip' => null,
    'personChip' => null,
    'gateProgress' => null,
    'progressLabel' => null,
    'backUrl' => null,
    'backLabel' => 'Back to Screening',
])

@php
    $modeLabel = match ($mode) {
        'committee' => 'Guided Committee',
        'post_approval' => 'Guided Post-Approval',
        default => 'Guided Screening',
    };
    $percent = max(0, min(100, (int) $percent));
    $backUrl = $backUrl ?: route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'overview']);
    $progressLabel = $progressLabel ?: match ($mode) {
        'committee' => 'Committee scan',
        'post_approval' => 'Post-approval',
        default => 'Overall Screening',
    };
@endphp

<x-admin.layout
    :title="$record->application_number.' · '.$modeLabel"
    heading=""
    :backUrl="$backUrl"
    :backLabel="$backLabel">

    <div class="max-w-xl mx-auto" data-guided-review>
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm bg-white"
             x-data="{ verdict: '', reason: '', missing: false }">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
                <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-brand-gold">Kopafasta Credit</p>
                <h1 class="text-xl font-bold mt-1">{{ $modeLabel }}</h1>
                <p class="text-sm text-white/80 mt-1 break-words">
                    {{ $record->application_number }}
                    @if ($record->product?->name)
                        · {{ $record->product->name }}
                    @else
                        · {{ $record->partyLabel() }}
                    @endif
                </p>
            </div>
            <div class="px-5 py-4 space-y-3 border-b border-slate-100">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    @if ($gateChip)
                        <p class="text-[11px] font-bold uppercase tracking-widest text-brand">{{ $gateChip }}</p>
                    @endif
                    @if ($personChip)
                        <p class="text-xs font-semibold text-slate-700 truncate">{{ $personChip }}</p>
                    @endif
                </div>
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <p class="text-xs font-semibold text-slate-600">{{ $progressLabel }} · {{ $percent }}%</p>
                        <p class="text-[11px] font-bold tabular-nums text-brand">{{ $percent }}%</p>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 overflow-hidden ring-1 ring-brand/10">
                        <div class="h-full rounded-full bg-brand-gold" style="width: {{ $percent }}%"></div>
                    </div>
                    @if ($gateProgress)
                        <p class="text-[11px] text-slate-500 mt-1.5">{{ $gateProgress }}</p>
                    @endif
                </div>
            </div>

            <div class="px-5 py-5 space-y-4">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="sticky bottom-0 z-20 border-t border-slate-200 bg-white px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</x-admin.layout>
