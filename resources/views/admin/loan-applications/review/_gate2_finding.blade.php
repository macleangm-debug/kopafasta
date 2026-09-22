@php
    $panel = is_array($panel ?? null) ? $panel : null;
    $comparison = is_array($comparison ?? null) ? $comparison : null;
    $capacity = is_array($capacity ?? null) ? $capacity : null;
    $gate2Checks = is_array($gate2Checks ?? null) ? $gate2Checks : null;
    if (! $panel && ($comparison || ($capacity && ($capacity['income_basis'] ?? '') === 'statement'))) {
        $panel = [
            'title' => 'VERIFIED REPAYMENT CAPACITY',
            'declared_label' => $declaredIncomeLabel ?? null,
            'declared_monthly' => $comparison['declared_monthly'] ?? ($capacity['declared_monthly'] ?? 0),
            'verified_monthly' => $comparison['statement_monthly'] ?? ($capacity['income'] ?? 0),
            'statement_total' => $statementTotal ?? 0,
            'difference' => $comparison['difference'] ?? null,
            'coverage_pct' => $comparison['coverage_pct'] ?? null,
            'discrepancy' => $comparison && (float) ($comparison['difference'] ?? 0) < 0,
            'discrepancy_note' => ($comparison && (float) ($comparison['difference'] ?? 0) < 0)
                ? 'The verified statement average is below the income declared during application. This does not by itself fail Gate 2. Repayment capacity is assessed using verified income below.'
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
            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $panel['title'] ?? 'VERIFIED REPAYMENT CAPACITY' }}</p>
            @php $chip = (string) ($panel['chip'] ?? 'WAITING'); @endphp
            <span @class([
                'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold ring-1',
                'bg-emerald-50 text-emerald-900 ring-emerald-200' => $chip === 'PASSED',
                'bg-rose-50 text-rose-950 ring-rose-200' => $chip === 'FAILED',
                'bg-amber-50 text-amber-950 ring-amber-200' => $chip === 'REFER',
                'bg-slate-50 text-slate-700 ring-slate-200' => ! in_array($chip, ['PASSED', 'FAILED', 'REFER'], true),
            ])>
                @if ($chip === 'PASSED') ✓ CAPACITY PASSED
                @elseif ($chip === 'FAILED') ✕ CAPACITY FAILED
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

        <div class="grid gap-3 md:grid-cols-2">
            <div class="rounded-lg bg-slate-50 ring-1 ring-slate-100 px-3 py-2 space-y-2">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Verified income</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Monthly evidence</dt>
                        <dd class="font-semibold text-right">{{ format_money((float) ($panel['verified_monthly'] ?? 0)) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Policy</dt>
                        <dd class="font-semibold text-right">{{ $panel['ratio_label'] ?? '' }}</dd>
                    </div>
                    @if (filled($panel['declared_label'] ?? null) || (float) ($panel['declared_monthly'] ?? 0) > 0)
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Declared</dt>
                            <dd class="font-semibold text-right">
                                @if (filled($panel['declared_label'] ?? null))
                                    {{ $panel['declared_label'] }}
                                    @if ((float) ($panel['declared_monthly'] ?? 0) > 0)
                                        · {{ format_money((float) $panel['declared_monthly']) }}
                                    @endif
                                @else
                                    {{ format_money((float) ($panel['declared_monthly'] ?? 0)) }}
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ((float) ($panel['statement_total'] ?? 0) > 0)
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">6-month deposits</dt>
                            <dd class="font-semibold text-right">{{ format_money((float) $panel['statement_total']) }}</dd>
                        </div>
                    @endif
                    @if ($panel['difference'] !== null)
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Vs declared</dt>
                            <dd class="font-semibold text-right">{{ ((float) $panel['difference'] < 0 ? '-' : '').format_money(abs((float) $panel['difference'])) }}@if ($panel['coverage_pct'] !== null) · {{ number_format((float) $panel['coverage_pct'], 1) }}%@endif</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-lg bg-slate-50 ring-1 ring-slate-100 px-3 py-2 space-y-2">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">Repayment</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Required</dt>
                        <dd class="font-semibold text-right">{{ format_money((float) ($panel['required_repayment'] ?? 0)) }} / {{ $panel['repayment_cadence'] ?? 'monthly' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Supported</dt>
                        <dd class="font-semibold text-right">{{ format_money((float) ($panel['max_repayment'] ?? 0)) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ (float) ($panel['shortfall'] ?? 0) > 0 ? 'Shortfall' : 'Headroom' }}</dt>
                        <dd class="font-semibold text-right">{{ format_money((float) ((float) ($panel['shortfall'] ?? 0) > 0 ? $panel['shortfall'] : ($panel['headroom'] ?? 0))) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="space-y-1">
            <p class="text-sm font-bold text-slate-900">{{ $panel['capacity_result'] ?: ($chip === 'PASSED' ? '✓ CAPACITY PASSED' : ($chip === 'FAILED' ? '✕ CAPACITY FAILED' : 'WAITING')) }}</p>
            @if (filled($panel['assessment'] ?? null))
                <p class="text-sm text-slate-800">{{ $panel['assessment'] }}</p>
            @endif
            @if (filled($panel['continue'] ?? null))
                <p class="text-sm font-semibold text-slate-900 mt-1">{{ $panel['continue'] }}</p>
            @endif
        </div>

        @if (is_array($gate2Checks) && $gate2Checks !== [])
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-1 text-sm border-t border-slate-100 pt-2">
                @foreach ($gate2Checks as $check)
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-slate-600">{{ $check['label'] ?? 'Check' }}</dt>
                        <dd class="font-bold {{ ($check['chip'] ?? '') === 'PASSED' ? 'text-emerald-800' : (($check['chip'] ?? '') === 'FAILED' ? 'text-rose-800' : 'text-amber-800') }}">{{ $check['chip'] ?? 'WAITING' }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>
@endif
