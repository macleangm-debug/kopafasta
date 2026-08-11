@props([
    'suffix',
    'settings' => [],
    'showSlaDays' => false,
])

@php
    $s = $settings;
    $enabled = (bool) ($s['enabled'] ?? false);
    $strategy = $s['strategy'] ?? 'least_load';
    $maxOpen = $s['max_open'] ?? null;
    $requireRegion = (bool) ($s['require_region'] ?? false);
    $reassignOnSla = (bool) ($s['reassign_on_sla'] ?? false);
    $weightLoad = (int) ($s['weight_load'] ?? 50);
    $weightEfficiency = (int) ($s['weight_efficiency'] ?? 40);
    $weightFairness = (int) ($s['weight_fairness'] ?? 10);
    $coldStart = (float) ($s['cold_start_rate'] ?? 50);
    $slaDays = (int) ($s['sla_days'] ?? 5);
    $strategies = config('partner_auto_assign.strategies', []);
    $strategyLabel = $strategies[$strategy] ?? $strategy;
    $maxOpenLabel = filled($maxOpen) ? (string) $maxOpen : 'No limit';
@endphp

<div class="mt-2 rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 p-4 space-y-4"
     x-data="{ editing: false }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-brand">Auto-assign rules</p>
            <p class="text-[11px] text-gray-500 mt-0.5">Shown read-only until you click Edit. Then save the page.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" x-show="!editing" @click="editing = true"
                    class="inline-flex items-center rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-brand ring-1 ring-brand/20 hover:bg-brand-muted/40">
                Edit
            </button>
            <button type="button" x-show="editing" x-cloak @click="editing = false"
                    class="inline-flex items-center rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">
                Cancel edit
            </button>
        </div>
    </div>

    {{-- View mode --}}
    <div x-show="!editing" class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Enabled</p>
                <p class="font-semibold text-gray-900 mt-0.5">{{ $enabled ? 'Yes' : 'No' }}</p>
            </div>
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Strategy</p>
                <p class="font-semibold text-gray-900 mt-0.5">{{ $strategyLabel }}</p>
            </div>
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Max open roster</p>
                <p class="font-semibold text-gray-900 mt-0.5">{{ $maxOpenLabel }}</p>
                <p class="text-[11px] text-gray-500 mt-1">Cap on open tasks per partner. Empty = unlimited.</p>
            </div>
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Cold-start efficiency %</p>
                <p class="font-semibold text-gray-900 mt-0.5">{{ $coldStart }}</p>
                <p class="text-[11px] text-gray-500 mt-1">Assumed score for new partners until real KPIs exist.</p>
            </div>
            @if ($showSlaDays)
                <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Task SLA days</p>
                    <p class="font-semibold text-gray-900 mt-0.5">{{ $slaDays }}</p>
                </div>
            @endif
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Region match</p>
                <p class="font-semibold text-gray-900 mt-0.5">{{ $requireRegion ? 'Required' : 'Soft' }}</p>
            </div>
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Reassign on SLA</p>
                <p class="font-semibold text-gray-900 mt-0.5">{{ $reassignOnSla ? 'Yes' : 'No' }}</p>
            </div>
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Weights (load · eff · fair)</p>
                <p class="font-semibold text-gray-900 mt-0.5">{{ $weightLoad }} · {{ $weightEfficiency }} · {{ $weightFairness }}</p>
            </div>
        </div>
    </div>

    {{-- Persist values when not editing (only these inputs are in the form) --}}
    <template x-if="!editing">
        <div>
            <input type="hidden" name="auto_assign_enabled_{{ $suffix }}" value="{{ $enabled ? '1' : '0' }}">
            <input type="hidden" name="auto_assign_strategy_{{ $suffix }}" value="{{ $strategy }}">
            <input type="hidden" name="auto_assign_max_open_{{ $suffix }}" value="{{ $maxOpen }}">
            <input type="hidden" name="auto_assign_cold_start_{{ $suffix }}" value="{{ $coldStart }}">
            @if ($showSlaDays)
                <input type="hidden" name="auto_assign_sla_days_{{ $suffix }}" value="{{ $slaDays }}">
            @endif
            <input type="hidden" name="auto_assign_weight_load_{{ $suffix }}" value="{{ $weightLoad }}">
            <input type="hidden" name="auto_assign_weight_efficiency_{{ $suffix }}" value="{{ $weightEfficiency }}">
            <input type="hidden" name="auto_assign_weight_fairness_{{ $suffix }}" value="{{ $weightFairness }}">
            <input type="hidden" name="auto_assign_require_region_{{ $suffix }}" value="{{ $requireRegion ? '1' : '0' }}">
            <input type="hidden" name="auto_assign_reassign_on_sla_{{ $suffix }}" value="{{ $reassignOnSla ? '1' : '0' }}">
        </div>
    </template>

    <template x-if="editing">
        <div class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
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
                    <p class="mt-1 text-[11px] text-gray-500">Max open tasks before a partner stops receiving new auto-assignments.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Cold-start efficiency %</label>
                    <input type="number" name="auto_assign_cold_start_{{ $suffix }}" value="{{ $coldStart }}" min="0" max="100" step="1"
                           class="w-full rounded-lg border-gray-300 text-sm">
                    <p class="mt-1 text-[11px] text-gray-500">Placeholder efficiency for new partners with no history.</p>
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
        </div>
    </template>
</div>
