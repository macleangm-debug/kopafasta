<x-admin.layout title="Partner performance" heading="Partner performance" subheading="Bands, coaching warnings, and automatic suspension for field partners">
    @include('admin.settings._tabs', ['active' => 'partner-performance'])

    <x-admin.settings-editor
        action="{{ route('admin.settings.partner-performance.save') }}"
        submit-label="Save partner performance settings"
        :tabs="[
            'scope' => 'Who this applies to',
            'bands' => 'Score bands',
            'weights' => 'Weights',
            'coaching' => 'Coaching',
            'recovery' => 'Recovery',
            'terms' => 'Terms',
        ]"
    >
        <x-admin.settings-panel id="scope">
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 space-y-3">
                <h3 class="text-sm font-semibold text-gray-900">Who this applies to</h3>
                <p class="text-xs text-gray-500">
                    Valuers, GPS, insurance, call centre, collectors, auctioneers, and legal partners.
                    Towing, yard, supplier, and capital are not scored here unless they already have jobs or cases.
                    Affiliates use <a href="{{ route('admin.settings.affiliates') }}" class="font-semibold text-brand hover:underline">Affiliate settings</a>.
                </p>
                <p class="text-xs text-gray-600">
                    {{ collect($categories)->map(fn ($key) => ucfirst(str_replace('_', ' ', $key)))->implode(' · ') }}
                </p>
                <p class="text-xs text-gray-500">
                    Live board:
                    <a href="{{ route('admin.partners.efficiency') }}" class="font-semibold text-brand hover:underline">Partner efficiency</a>.
                </p>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="bands">
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Score bands</h3>
                    <p class="text-xs text-gray-500 mt-1">A partner needs this many closed jobs before they get a score. Below that they stay New.</p>
                </div>
                <div class="grid md:grid-cols-3 gap-4">
                    <x-admin.input name="min_jobs_for_score" label="Closed jobs before a score" type="number" min="1"
                                   :value="$values['min_jobs_for_score'] ?? 3" />
                    <x-admin.input name="strong_score" label="Strong (score at or above)" type="number" min="1" max="100"
                                   :value="$values['strong_score'] ?? 80" />
                    <x-admin.input name="watch_score" label="Watch / Needs attention (score at or above)" type="number" min="1" max="99"
                                   :value="$values['watch_score'] ?? 60" />
                    <x-admin.input name="excellent_score" label="Excellent (score at or above)" type="number" min="1" max="100"
                                   :value="$values['excellent_score'] ?? 90" />
                </div>
                <p class="text-xs text-gray-500">Below Watch is At risk internally (presented as At risk). Escalate or fail rates at or above the % below also force At risk. Presentation: New/Ramp-up · Excellent · Good standing · Needs attention · At risk · Suspended.</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <x-admin.input name="target_on_time_percent" label="On-time target %" type="number" step="1" min="0" max="100"
                                   :value="$values['target_on_time_percent'] ?? 90" />
                    <x-admin.input name="target_completion_percent" label="Completion target %" type="number" step="1" min="0" max="100"
                                   :value="$values['target_completion_percent'] ?? 95" />
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <x-admin.input name="force_at_risk_escalation_percent" label="Force coaching if escalated ≥ %" type="number" step="1" min="0" max="100"
                                   :value="$values['force_at_risk_escalation_percent'] ?? 40" />
                    <x-admin.input name="force_at_risk_fail_percent" label="Force coaching if failed ≥ %" type="number" step="1" min="0" max="100"
                                   :value="$values['force_at_risk_fail_percent'] ?? 40" />
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="weights">
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Score weights (must add toward 100)</h3>
                    <p class="text-xs text-gray-500 mt-1">How much each behaviour counts. Default is Bolt-style: finish the job, finish on time, do not escalate, do not fail.</p>
                </div>
                <div class="grid md:grid-cols-4 gap-4">
                    <x-admin.input name="weight_completion" label="Completed %" type="number" min="0" max="100"
                                   :value="$values['weight_completion'] ?? 40" />
                    <x-admin.input name="weight_on_time" label="On-time %" type="number" min="0" max="100"
                                   :value="$values['weight_on_time'] ?? 25" />
                    <x-admin.input name="weight_not_escalated" label="Not escalated %" type="number" min="0" max="100"
                                   :value="$values['weight_not_escalated'] ?? 20" />
                    <x-admin.input name="weight_not_failed" label="Not failed %" type="number" min="0" max="100"
                                   :value="$values['weight_not_failed'] ?? 15" />
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="coaching">
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Coaching and automatic suspension</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        We never terminate on the first miss. First we nudge. After repeated At risk reviews, we suspend (portal off, open jobs released). Admin can reactivate.
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="auto_nudge" value="0">
                    <input type="checkbox" name="auto_nudge" value="1"
                           @checked((bool) ($values['auto_nudge'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Send a performance warning (SMS / in-app)
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="auto_suspend" value="0">
                    <input type="checkbox" name="auto_suspend" value="1"
                           @checked((bool) ($values['auto_suspend'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Automatically suspend after repeated at-risk reviews
                </label>
                <div class="grid md:grid-cols-2 gap-4">
                    <x-admin.input name="warnings_before_suspend" label="At-risk reviews before suspend" type="number" min="1"
                                   :value="$values['warnings_before_suspend'] ?? 2" />
                    <x-admin.input name="nudge_cooldown_days" label="Days between warnings" type="number" min="1"
                                   :value="$values['nudge_cooldown_days'] ?? 7" />
                </div>
                <p class="text-xs text-gray-500">Weekly job: <span class="font-mono">php artisan partners:evaluate-efficiency</span> (Mondays 06:30).</p>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="recovery">
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Automatic performance recovery</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Restores a partner suspended for performance when the lookback window is no longer at-risk and meets the on-time and completion targets.
                        Does not undo compliance, fraud, safety, or administrative disable.
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="auto_recover" value="0">
                    <input type="checkbox" name="auto_recover" value="1"
                           @checked((bool) ($values['auto_recover'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Automatically restore performance standing when the recovery condition is met
                </label>
                <x-admin.input name="recover_lookback_days" label="Recovery lookback (days)" type="number" min="1"
                               :value="$values['recover_lookback_days'] ?? 90" />
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="terms">
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 space-y-4">
                @php $terms = $terms ?? []; @endphp
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Partner Terms</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Templates live in language files and render SLA/KPI numbers from Origination auto-assignment, Recovery policy, and this page.
                        Do not duplicate those numbers here.
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="require_terms_before_jobs" value="0">
                    <input type="checkbox" name="require_terms_before_jobs" value="1"
                           @checked((bool) ($terms['require_before_jobs'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Require current Terms before the partner can receive work
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="material_change_requires_reacceptance" value="0">
                    <input type="checkbox" name="material_change_requires_reacceptance" value="1"
                           @checked((bool) ($terms['material_change_requires_reacceptance'] ?? false))
                           class="rounded border-gray-300 text-brand">
                    Material Terms/policy version changes require re-acceptance
                </label>
                <div class="grid md:grid-cols-2 gap-4">
                    <x-admin.input name="policy_version" label="Policy version" type="number" min="1"
                                   :value="$terms['policy_version'] ?? 1" />
                    <x-admin.input name="conduct_version" label="Conduct version"
                                   :value="$terms['conduct_version'] ?? '2026.09'" />
                </div>
                <p class="text-xs text-gray-500">Launched {{ $terms['launched_at'] ?? 'on first save' }}. Partners activated before that date keep the legacy conduct checkbox until re-acceptance is required.</p>
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
