@php
    $card = $identityCard ?? [];
    $ticks = $card['ticks'] ?? [];
    $nok = $card['nok'] ?? [];
    $lgo = $card['lgo'] ?? [];
    $spouse = $card['spouse'] ?? [];
@endphp
<div class="rounded-2xl ring-1 ring-brand/15 bg-white px-4 py-3.5 space-y-3">
    <p class="text-sm font-bold text-slate-900">{{ $card['name'] ?? 'Person' }} — Identity & contacts</p>
    <ul class="grid sm:grid-cols-2 gap-1.5 text-[12px]">
        @foreach ($ticks as $tick)
            @php
                $mark = match ($tick['state'] ?? 'open') {
                    'ok' => '✓',
                    'fail' => '✕',
                    'warn' => '⚠',
                    default => '○',
                };
                $tone = match ($tick['state'] ?? 'open') {
                    'ok' => 'text-emerald-800',
                    'fail' => 'text-rose-800',
                    'warn' => 'text-amber-800',
                    default => 'text-slate-600',
                };
            @endphp
            <li class="{{ $tone }} font-semibold">{{ $mark }} {{ $tick['short'] ?? $tick['label'] ?? 'Check' }}</li>
        @endforeach
    </ul>

    <div class="grid sm:grid-cols-2 gap-3">
        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Next of kin</p>
            @if (! empty($nok['missing']))
                <p class="text-sm font-semibold text-amber-950 mt-1">Next-of-kin contact missing</p>
                <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'profiles', 'tab' => 'personal']).'#next-of-kin' }}"
                   class="inline-flex mt-1 text-[11px] font-bold text-brand underline">Request / correct next of kin</a>
            @else
                <p class="text-sm font-semibold text-slate-900 mt-1">{{ $nok['name'] }}</p>
                <p class="text-[12px] text-slate-600">{{ $nok['relationship'] ?? '' }}@if (! empty($nok['phone'])) · {{ $nok['phone'] }}@endif</p>
                <p class="text-[11px] font-semibold text-slate-500 mt-1">Review contact in the checklist below</p>
            @endif
        </div>
        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Local Government Officer</p>
            @if (! empty($lgo['missing']))
                <p class="text-sm font-semibold text-amber-950 mt-1">LGO contact missing</p>
                <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'profiles', 'tab' => 'personal']) }}"
                   class="inline-flex mt-1 text-[11px] font-bold text-brand underline">Open residence / LGO</a>
            @else
                <p class="text-sm font-semibold text-slate-900 mt-1">{{ $lgo['name'] }}</p>
                <p class="text-[12px] text-slate-600">{{ $lgo['position'] ?? '' }}@if (! empty($lgo['phone'])) · {{ $lgo['phone'] }}@endif</p>
                <p class="text-[11px] font-semibold text-slate-500 mt-1">Record verification in Residence below</p>
            @endif
        </div>
    </div>

    <div class="rounded-xl bg-slate-50 px-3 py-2.5">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Spouse</p>
        @if (empty($spouse['applicable']))
            <p class="text-sm font-semibold text-emerald-900 mt-1">N/A — System checked</p>
        @elseif (! empty($spouse['name']))
            <p class="text-sm font-semibold text-slate-900 mt-1">{{ $spouse['name'] }}</p>
            <p class="text-[12px] text-slate-600">Telephone is not stored on this profile — do not invent a number.</p>
        @else
            <p class="text-sm font-semibold text-amber-950 mt-1">Married — spouse name missing</p>
        @endif
    </div>
</div>
