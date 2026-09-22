@php
    $panel = is_array($panel ?? null) ? $panel : null;
    $comparison = is_array($comparison ?? null) ? $comparison : null;
    $capacity = is_array($capacity ?? null) ? $capacity : null;
    if (! $panel && ($comparison || ($capacity && ($capacity['income_basis'] ?? '') === 'statement'))) {
        $panel = [
            'title' => 'GATE 2 — VERIFIED AFFORDABILITY',
            'declared_label' => $declaredIncomeLabel ?? null,
            'declared_monthly' => $comparison['declared_monthly'] ?? ($capacity['declared_monthly'] ?? 0),
            'verified_monthly' => $comparison['statement_monthly'] ?? ($capacity['income'] ?? 0),
            'statement_total' => $statementTotal ?? 0,
            'difference' => $comparison['difference'] ?? null,
            'coverage_pct' => $comparison['coverage_pct'] ?? null,
            'discrepancy' => $comparison && (float) ($comparison['difference'] ?? 0) < 0,
            'discrepancy_note' => ($comparison && (float) ($comparison['difference'] ?? 0) < 0)
                ? 'The verified statement average is below the income declared during application. This does not by itself fail Gate 2. Repayment capacity is assessed using the applicable verified-income policy below.'
                : null,
            'required_label' => $capacity['required_label'] ?? 'Required loan repayment',
            'required_repayment' => $capacity['proposed_repayment'] ?? 0,
            'repayment_cadence' => $capacity['repayment_cadence'] ?? 'monthly',
            'ratio_label' => $capacity['ratio_label'] ?? '',
            'max_repayment' => $capacity['max_repayment'] ?? 0,
            'headroom' => $capacity['headroom'] ?? 0,
            'shortfall' => $capacity['shortfall'] ?? 0,
            'assessment' => $capacity['assessment'] ?? '',
            'capacity_result' => $capacity['result'] ?? '',
            'chip' => $capacity['gate_chip'] ?? 'WAITING',
            'continue' => '',
        ];
    }
@endphp

@if ($panel)
    <div data-gate2-conclusion class="rounded-xl ring-1 ring-brand/20 bg-white px-3.5 py-3 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $panel['title'] }}</p>
            @php $chip = (string) ($panel['chip'] ?? 'WAITING'); @endphp
            <span @class([
                'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1',
                'bg-emerald-50 text-emerald-900 ring-emerald-200' => $chip === 'PASSED',
                'bg-rose-50 text-rose-950 ring-rose-200' => $chip === 'FAILED',
                'bg-amber-50 text-amber-950 ring-amber-200' => $chip === 'REFER',
                'bg-slate-50 text-slate-700 ring-slate-200' => ! in_array($chip, ['PASSED', 'FAILED', 'REFER'], true),
            ])>
                @if ($chip === 'PASSED') ✓ PASSED
                @elseif ($chip === 'FAILED') ✕ FAILED
                @elseif ($chip === 'REFER') ! REFER
                @else WAITING
                @endif
            </span>
        </div>

        @if (! empty($panel['discrepancy']))
            <div class="rounded-lg bg-amber-50 ring-1 ring-amber-200 px-3 py-2 space-y-1">
                <p class="text-[10px] uppercase tracking-widest text-amber-800 font-bold">Income discrepancy</p>
                <p class="text-sm text-amber-950">{{ $panel['discrepancy_note'] }}</p>
            </div>
        @endif

        <dl class="grid sm:grid-cols-2 gap-2 text-sm">
            @if (filled($panel['declared_label'] ?? null))
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-slate-500">Declared income / range</dt>
                    <dd class="font-semibold">{{ $panel['declared_label'] }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-slate-500">Declared monthly (policy input)</dt>
                <dd class="font-semibold">{{ format_money((float) ($panel['declared_monthly'] ?? 0)) }}</dd>
            </div>
            @if ((float) ($panel['statement_total'] ?? 0) > 0)
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-slate-500">6-month statement deposits</dt>
                    <dd class="font-semibold">{{ format_money((float) $panel['statement_total']) }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-slate-500">Verified monthly average</dt>
                <dd class="font-semibold">{{ format_money((float) ($panel['verified_monthly'] ?? 0)) }}</dd>
            </div>
            @if ($panel['difference'] !== null)
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-slate-500">Difference</dt>
                    <dd class="font-semibold">{{ ((float) $panel['difference'] < 0 ? '-' : '').format_money(abs((float) $panel['difference'])) }}@if ($panel['coverage_pct'] !== null) · {{ number_format((float) $panel['coverage_pct'], 1) }}%@endif</dd>
                </div>
            @endif
        </dl>

        <dl class="grid sm:grid-cols-2 gap-2 text-sm rounded-lg bg-slate-50 ring-1 ring-slate-100 px-3 py-2">
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-slate-500">{{ $panel['required_label'] ?? 'Required repayment' }}</dt>
                <dd class="font-semibold">{{ format_money((float) ($panel['required_repayment'] ?? 0)) }} / {{ $panel['repayment_cadence'] ?? 'monthly' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-slate-500">Configured affordability rule</dt>
                <dd class="font-semibold">{{ $panel['ratio_label'] ?? '' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-slate-500">Evidence supports (maximum)</dt>
                <dd class="font-semibold">{{ format_money((float) ($panel['max_repayment'] ?? 0)) }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-slate-500">{{ (float) ($panel['shortfall'] ?? 0) > 0 ? 'Shortfall' : 'Headroom' }}</dt>
                <dd class="font-semibold">{{ format_money((float) ((float) ($panel['shortfall'] ?? 0) > 0 ? $panel['shortfall'] : ($panel['headroom'] ?? 0))) }}</dd>
            </div>
        </dl>

        <div class="space-y-1">
            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">System assessment</p>
            <p class="text-sm text-slate-800">{{ $panel['assessment'] ?: ($panel['capacity_result'] ?? '') }}</p>
            @if (filled($panel['continue'] ?? null))
                <p class="text-sm font-semibold text-slate-900 mt-1">{{ $panel['continue'] }}</p>
            @endif
        </div>
    </div>
@endif
