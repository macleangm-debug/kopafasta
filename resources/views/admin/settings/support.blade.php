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
    $personaNames = old('persona_names', $personaNames ?? ['Amani', 'Neema', 'Baraka', 'Rehema', 'Daniel']);
    while (count($personaNames) < 5) {
        $personaNames[] = '';
    }
    $personaNames = array_slice(array_values($personaNames), 0, 5);
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
        :tabs="['issues' => 'Issues & SLA', 'priority' => 'Priority fallbacks', 'recurring' => 'Recurring flags', 'msaidizi' => 'Automated assistant']"
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

        <div x-show="tab === 'msaidizi'" x-cloak class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-8">
            <div class="space-y-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold">Automated assistant personas</p>
                    <p class="text-sm text-gray-600 mt-1">
                        Up to <strong>5</strong> named digital assistants. Empty slots are ignored.
                        Conversations pick one of the active names at start. Leave all blank to restore built-in defaults.
                    </p>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    @foreach ($personaNames as $i => $name)
                        <label class="block text-sm">
                            <span class="font-semibold text-gray-700">Persona {{ $i + 1 }}</span>
                            <input type="text" name="persona_names[{{ $i }}]" maxlength="40"
                                   value="{{ $name }}"
                                   class="mt-1 w-full rounded-lg border-gray-200 text-sm"
                                   placeholder="Name">
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">Guest conversion closings</p>
                <p class="text-sm text-gray-600 mt-1">
                    After a Guest resolves with <strong>Ndiyo</strong>, one of these variants rotates naturally.
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
