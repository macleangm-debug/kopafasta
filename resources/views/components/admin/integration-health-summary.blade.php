@props([
    'partnerKey',
    'summary' => null,
])

@php
    $summary = $summary ?? app(\App\Services\Integrations\IntegrationFeedback::class)->persistentSummary($partnerKey);
    $state = $summary['state'] ?? 'neutral';
    $tone = match ($state) {
        'success' => 'text-emerald-700',
        'warning' => 'text-amber-700',
        'error' => 'text-rose-700',
        default => 'text-slate-600',
    };
    $last = $summary['last_tested_at'] ?? null;
@endphp

<div {{ $attributes->class('rounded-xl bg-slate-50 ring-1 ring-slate-200 px-3.5 py-3') }}>
    <p class="text-[10px] uppercase tracking-[0.16em] font-semibold text-slate-500">Health</p>
    <p class="mt-1 text-sm font-bold {{ $tone }}">{{ $summary['headline'] ?? 'Not tested' }}</p>
    @if (! empty($summary['detail']))
        <p class="mt-0.5 text-xs text-slate-600">{{ $summary['detail'] }}</p>
    @endif
    @if ($last)
        <p class="mt-1 text-[11px] text-slate-500">
            Last tested: {{ \Illuminate\Support\Carbon::parse($last)->timezone(config('app.timezone'))->format('j M Y, H:i') }}
        </p>
    @endif
</div>
