@php
    $categories = $taxonomy['categories'] ?? [];
    $defaultPriority = $taxonomy['default_priority'] ?? [];
    $targets = $taxonomy['target_resolution_minutes'] ?? [];
    $approaching = $taxonomy['approaching_threshold_percent'] ?? [];
    $conversion = $guestConversionClosings ?? ['sw' => [], 'en' => []];
    $swClosings = old('conversion_sw', $conversion['sw'] ?? []);
    $enClosings = old('conversion_en', $conversion['en'] ?? []);
    while (count($swClosings) < 5) {
        $swClosings[] = '';
    }
    while (count($enClosings) < 5) {
        $enClosings[] = '';
    }
    $personasMax = (int) old('personas_max', $personasMax ?? 20);
    $personaRows = old('persona_rows', $personaRows ?? null);
    if (! is_array($personaRows) || $personaRows === []) {
        $names = old('persona_names', $personaNames ?? ['Amani', 'Neema', 'Baraka', 'Rehema', 'Daniel']);
        $personaRows = [];
        foreach (array_values($names) as $i => $name) {
            if (trim((string) $name) === '') {
                continue;
            }
            $personaRows[] = [
                'key' => \Illuminate\Support\Str::slug((string) $name) ?: ('persona_'.($i + 1)),
                'name' => (string) $name,
                'active' => true,
            ];
        }
    }
    $guestConversionEnabled = old('guest_conversion_enabled', $guestConversionEnabled ?? true);
    $guestRepeatThreshold = (int) old('guest_repeat_threshold', $guestRepeatThreshold ?? 3);
    $guestConversionCooldownHours = (int) old('guest_conversion_cooldown_hours', $guestConversionCooldownHours ?? 72);
@endphp

<x-admin.layout title="Support SLA & Priorities" heading="Support SLA & Priorities" subheading="Issue → default priority → target resolution → approaching threshold. Snapshotted onto each ticket at create.">
    @include('admin.settings._tabs', ['active' => 'support-sla'])

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-950">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
    @endif

    <x-admin.settings-editor
        action="{{ route('admin.settings.support.save') }}"
        submit-label="Save Support settings"
        :tabs="['issues' => 'Issues & SLA', 'priority' => 'Priority fallbacks', 'recurring' => 'Recurring flags', 'msaidizi' => 'Digital Assistants']"
        default-tab="issues"
    >
        <div x-show="tab === 'issues'" x-cloak class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">Issue SLA matrix</p>
                <p class="text-sm text-gray-600 mt-1">
                    Each ticket snapshots these values at create (<code class="text-xs bg-gray-100 px-1 rounded">sla_due_at</code>,
                    <code class="text-xs bg-gray-100 px-1 rounded">sla_target_minutes</code>,
                    <code class="text-xs bg-gray-100 px-1 rounded">sla_approaching_pct</code>). Later edits never rewrite open tickets.
                </p>
            </div>

            <div class="overflow-x-auto rounded-xl ring-1 ring-gray-100">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-3 py-2.5">Issue</th>
                            <th class="px-3 py-2.5">Default priority</th>
                            <th class="px-3 py-2.5">Target minutes</th>
                            <th class="px-3 py-2.5">Approaching %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($categories as $key => $label)
                            <tr>
                                <td class="px-3 py-2.5 font-semibold text-gray-900 whitespace-nowrap">{{ $label }}</td>
                                <td class="px-3 py-2.5">
                                    <select name="default_priority[{{ $key }}]" class="w-full rounded-lg border-gray-200 text-sm">
                                        @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $p => $pLabel)
                                            <option value="{{ $p }}" @selected(old("default_priority.$key", $defaultPriority[$key] ?? 'normal') === $p)>{{ $pLabel }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="number" min="15" max="10080" name="target_minutes[{{ $key }}]"
                                           value="{{ old("target_minutes.$key", $targets[$key] ?? 480) }}"
                                           class="w-28 rounded-lg border-gray-200 text-sm tabular-nums">
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="number" min="50" max="95" name="approaching_pct[{{ $key }}]"
                                           value="{{ old("approaching_pct.$key", $approaching[$key] ?? 80) }}"
                                           class="w-20 rounded-lg border-gray-200 text-sm tabular-nums">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="tab === 'priority'" x-cloak class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-5">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">Priority fallback minutes</p>
                <p class="text-sm text-gray-600 mt-1">Used when an Issue has no override (<code class="text-xs bg-gray-100 px-1 rounded">support.sla.minutes.{'{'}priority{'}'}</code>).</p>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach (['urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal', 'low' => 'Low'] as $p => $pLabel)
                    <label class="block text-sm">
                        <span class="font-semibold text-gray-700">{{ $pLabel }} (minutes)</span>
                        <input type="number" min="15" max="10080" name="priority_minutes[{{ $p }}]"
                               value="{{ old("priority_minutes.$p", $priorityDefaults[$p] ?? 480) }}"
                               class="mt-1 w-full rounded-lg border-gray-200 text-sm tabular-nums">
                    </label>
                @endforeach
            </div>
            <label class="block text-sm max-w-xs">
                <span class="font-semibold text-gray-700">Global approaching threshold %</span>
                <input type="number" min="50" max="95" name="warning_percent"
                       value="{{ old('warning_percent', $warningPercent) }}"
                       class="mt-1 w-full rounded-lg border-gray-200 text-sm tabular-nums">
                <span class="block text-xs text-gray-500 mt-1">Stored as <code>support.sla.warning_percent</code>. Per-Issue values above take precedence at create.</span>
            </label>
        </div>

        <div x-show="tab === 'recurring'" x-cloak class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-5">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">Recurring Issue flag</p>
                <p class="text-sm text-gray-600 mt-1">
                    Flag an Issue on Support Home when ticket volume reaches the threshold within the window.
                    Aggregate only — no auto-merge.
                </p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4 max-w-xl">
                <label class="block text-sm">
                    <span class="font-semibold text-gray-700">Flag when Issue reaches X tickets</span>
                    <input type="number" min="2" max="100" name="recurring_count"
                           value="{{ old('recurring_count', $recurringCount) }}"
                           class="mt-1 w-full rounded-lg border-gray-200 text-sm tabular-nums">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-gray-700">Within Y hours</span>
                    <input type="number" min="1" max="720" name="recurring_window_hours"
                           value="{{ old('recurring_window_hours', $recurringWindowHours) }}"
                           class="mt-1 w-full rounded-lg border-gray-200 text-sm tabular-nums">
                </label>
            </div>
        </div>

        <div x-show="tab === 'msaidizi'" x-cloak class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-8"
             x-data="{
                max: {{ (int) $personasMax }},
                rows: @js(collect($personaRows)->values()->all()),
                add() {
                    if (this.rows.length >= this.max) return;
                    this.rows.push({ key: '', name: '', active: true });
                },
                remove(i) { this.rows.splice(i, 1); }
             }">
            <div class="space-y-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold">Digital Assistants</p>
                    <p class="text-sm text-gray-600 mt-1">
                        Settings-backed personas of one Digital Assistant engine (not Staff users).
                        Active names rotate into new conversations. Inactive keep historical attribution but receive no new chats.
                    </p>
                </div>
                <div class="grid sm:grid-cols-3 gap-3 max-w-xl">
                    <label class="block text-sm sm:col-span-1">
                        <span class="font-semibold text-gray-700">Maximum assistants</span>
                        <input type="number" name="personas_max" min="1" max="20" x-model.number="max" value="{{ $personasMax }}"
                               class="mt-1 w-full rounded-lg border-gray-200 text-sm">
                        <span class="text-xs text-gray-500">Soft ceiling (1–20). Not a requirement of exactly five.</span>
                    </label>
                </div>
                <div class="space-y-3">
                    <template x-for="(row, i) in rows" :key="'persona-'+i">
                        <div class="flex flex-col sm:flex-row sm:items-end gap-3 rounded-xl ring-1 ring-slate-200 bg-slate-50/60 p-3">
                            <input type="hidden" :name="'persona_keys['+i+']'" :value="row.key || ''">
                            <label class="block text-sm flex-1 min-w-0">
                                <span class="font-semibold text-gray-700">Name</span>
                                <input type="text" :name="'persona_names['+i+']'" maxlength="40" x-model="row.name"
                                       class="mt-1 w-full rounded-lg border-gray-200 text-sm bg-white"
                                       placeholder="e.g. Amani">
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-800 pb-2 shrink-0">
                                <input type="hidden" :name="'persona_active['+i+']'" value="0">
                                <input type="checkbox" :name="'persona_active['+i+']'" value="1" x-model="row.active"
                                       class="rounded border-gray-300 text-brand focus:ring-brand">
                                Active
                            </label>
                            <button type="button" @click="remove(i)"
                                    class="text-xs font-semibold text-rose-700 hover:underline pb-2 shrink-0"
                                    x-show="rows.length > 1">Remove</button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="add()" :disabled="rows.length >= max"
                        class="inline-flex rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5 disabled:opacity-50">
                    Add Digital Assistant
                </button>
            </div>

            <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4 space-y-3">
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">Guest conversion</p>
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                    <input type="checkbox" name="guest_conversion_enabled" value="1" @checked($guestConversionEnabled) class="rounded border-gray-300 text-brand focus:ring-brand">
                    Enable gentle Guest conversion nudges
                </label>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="block text-sm">
                        <span class="font-semibold text-gray-700">Repeat-Guest threshold</span>
                        <input type="number" name="guest_repeat_threshold" min="2" max="20" value="{{ $guestRepeatThreshold }}"
                               class="mt-1 w-full rounded-lg border-gray-200 text-sm">
                        <span class="text-xs text-gray-500">Separate visits before a nudge may show</span>
                    </label>
                    <label class="block text-sm">
                        <span class="font-semibold text-gray-700">Nudge cooldown (hours)</span>
                        <input type="number" name="guest_conversion_cooldown_hours" min="1" max="720" value="{{ $guestConversionCooldownHours }}"
                               class="mt-1 w-full rounded-lg border-gray-200 text-sm">
                        <span class="text-xs text-gray-500">Do not repeat the nudge more often than this</span>
                    </label>
                </div>
            </div>

            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">Guest conversion closings</p>
                <p class="text-sm text-gray-600 mt-1">
                    After a Guest resolves with <strong>Ndiyo</strong>, or at configured repeat-Guest moments, one of these variants rotates naturally.
                    Use <code class="text-xs bg-gray-100 px-1 rounded">{name}</code> for the first name.
                    Leave blank to use built-in defaults. Members/Partners never see these Join invitations.
                </p>
            </div>
            <div class="grid lg:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <p class="text-sm font-semibold text-gray-800">Swahili (up to 5)</p>
                    @foreach ($swClosings as $i => $line)
                        <textarea name="conversion_sw[{{ $i }}]" rows="2"
                                  class="w-full rounded-lg border-gray-200 text-sm"
                                  placeholder="Variant {{ $i + 1 }}">{{ $line }}</textarea>
                    @endforeach
                </div>
                <div class="space-y-3">
                    <p class="text-sm font-semibold text-gray-800">English (up to 5)</p>
                    @foreach ($enClosings as $i => $line)
                        <textarea name="conversion_en[{{ $i }}]" rows="2"
                                  class="w-full rounded-lg border-gray-200 text-sm"
                                  placeholder="Variant {{ $i + 1 }}">{{ $line }}</textarea>
                    @endforeach
                </div>
            </div>
        </div>
    </x-admin.settings-editor>
</x-admin.layout>
