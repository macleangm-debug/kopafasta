@props([
    'suffix', // form field suffix, e.g. call_center or svc_valuer
    'settings' => [],
    'showSlaDays' => false,
    'alpinePrefix' => null, // optional alpine object path like item.auto_assign
])

@php
    $s = $settings;
    $enabled = (bool) ($s['enabled'] ?? false);
    $strategy = $s['strategy'] ?? 'least_load';
    $maxOpen = $s['max_open'] ?? '';
    $requireRegion = (bool) ($s['require_region'] ?? false);
    $reassignOnSla = (bool) ($s['reassign_on_sla'] ?? false);
    $weightLoad = (int) ($s['weight_load'] ?? 50);
    $weightEfficiency = (int) ($s['weight_efficiency'] ?? 40);
    $weightFairness = (int) ($s['weight_fairness'] ?? 10);
    $coldStart = (float) ($s['cold_start_rate'] ?? 50);
    $slaDays = (int) ($s['sla_days'] ?? 5);
    $strategies = config('partner_auto_assign.strategies', []);
@endphp

<div class="mt-4 rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 p-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-bold uppercase tracking-widest text-brand">Auto-assign</p>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="auto_assign_enabled_{{ $suffix }}" value="0">
            <input type="checkbox" name="auto_assign_enabled_{{ $suffix }}" value="1" @checked($enabled)
                   class="rounded border-gray-300 text-brand">
            Enable auto-assign
        </label>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Strategy</label>
            <select name="auto_assign_strategy_{{ $suffix }}" class="w-full rounded-lg border-gray-300 text-sm">
                @foreach ($strategies as $value => $label)
                    <option value="{{ $value }}" @selected($strategy === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Max open roster</label>
            <input type="number" name="auto_assign_max_open_{{ $suffix }}" value="{{ $maxOpen }}" min="1" max="500"
                   placeholder="No limit" class="w-full rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Cold-start efficiency %</label>
            <input type="number" name="auto_assign_cold_start_{{ $suffix }}" value="{{ $coldStart }}" min="0" max="100" step="1"
                   class="w-full rounded-lg border-gray-300 text-sm">
        </div>
        @if ($showSlaDays)
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Task SLA / lead days</label>
                <input type="number" name="auto_assign_sla_days_{{ $suffix }}" value="{{ $slaDays }}" min="1" max="90"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Weight · load</label>
            <input type="number" name="auto_assign_weight_load_{{ $suffix }}" value="{{ $weightLoad }}" min="0" max="100"
                   class="w-full rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Weight · efficiency</label>
            <input type="number" name="auto_assign_weight_efficiency_{{ $suffix }}" value="{{ $weightEfficiency }}" min="0" max="100"
                   class="w-full rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Weight · fairness</label>
            <input type="number" name="auto_assign_weight_fairness_{{ $suffix }}" value="{{ $weightFairness }}" min="0" max="100"
                   class="w-full rounded-lg border-gray-300 text-sm">
        </div>
    </div>

    <div class="flex flex-wrap gap-4 text-sm text-gray-700">
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="auto_assign_require_region_{{ $suffix }}" value="0">
            <input type="checkbox" name="auto_assign_require_region_{{ $suffix }}" value="1" @checked($requireRegion)
                   class="rounded border-gray-300 text-brand">
            Require region match
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="auto_assign_reassign_on_sla_{{ $suffix }}" value="0">
            <input type="checkbox" name="auto_assign_reassign_on_sla_{{ $suffix }}" value="1" @checked($reassignOnSla)
                   class="rounded border-gray-300 text-brand">
            Reassign if SLA missed (when another partner is available)
        </label>
    </div>
    <p class="text-[11px] text-gray-500">
        Eligibility: active partners only, under max open roster. Efficiency uses recovery KPIs for recovery types, and task completion rate for valuer / GPS / insurance. New partners use cold-start %.
    </p>
</div>
