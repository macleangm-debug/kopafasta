{{-- Partner match resolution panel — Applicant vs Existing Partner. --}}
<form method="POST" action="{{ route('admin.partner-applications.match-resolution', $application) }}" x-ref="matchResolveForm" class="hidden">
    @csrf
    <input type="hidden" name="decision" value="">
    <input type="hidden" name="partner_id" value="">
    <input type="hidden" name="confirm_despite_conflicts" value="0">
</form>

<x-site.action-panel title="Review match" open="matchOpen" size="xl">
    <div x-show="matchPhase === 'compare'" class="space-y-4">
        <template x-if="currentMatch()">
            <div class="space-y-4">
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-2 text-xs text-amber-950">
                    <p class="font-semibold" x-text="(currentMatch().applicant?.name || 'Applicant') + ' ↔ ' + (currentMatch().existing?.name || 'Partner')"></p>
                    <p class="mt-0.5" x-text="'Match: ' + (currentMatch().match_summary || '')"></p>
                    <p class="mt-0.5" x-show="(currentMatch().conflict_fields || []).length"
                       x-text="'Conflicts: ' + (currentMatch().conflict_fields || []).join(', ')"></p>
                </div>

                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="text-left font-semibold px-3 py-2 w-28"></th>
                                <th class="text-left font-semibold px-3 py-2">Applicant</th>
                                <th class="text-left font-semibold px-3 py-2">Existing Partner</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in (currentMatch().rows || [])" :key="'row-'+idx">
                                <tr class="border-t border-gray-100"
                                    :class="row.matched ? 'bg-emerald-50/70' : (row.conflict ? 'bg-rose-50/80' : '')">
                                    <td class="px-3 py-2 text-xs font-semibold text-gray-500 align-top" x-text="row.label"></td>
                                    <td class="px-3 py-2 text-gray-900 align-top">
                                        <span x-text="row.applicant"></span>
                                        <span x-show="row.matched" class="ml-1 text-[10px] font-bold text-emerald-700">✓</span>
                                        <span x-show="row.conflict" class="ml-1 text-[10px] font-bold text-rose-700">≠</span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-900 align-top">
                                        <span x-text="row.existing"></span>
                                        <span x-show="row.matched" class="ml-1 text-[10px] font-bold text-emerald-700">✓</span>
                                        <span x-show="row.conflict" class="ml-1 text-[10px] font-bold text-rose-700">≠</span>
                                    </td>
                                </tr>
                            </template>
                            <tr class="border-t border-gray-100 bg-brand-muted/30">
                                <td class="px-3 py-2 text-xs font-semibold text-gray-500">Match reason</td>
                                <td class="px-3 py-2 font-semibold text-brand" colspan="2" x-text="currentMatch().match_summary"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-amber-900 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2"
                   x-show="currentMatch().uniqueness?.email_shared_with_existing_login"
                   x-text="currentMatch().uniqueness?.message"></p>

                <div class="space-y-2 pt-1">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-gray-500">Resolution</p>
                    <p class="text-xs text-gray-500">The system identifies the collision. You decide the relationship between the identities.</p>

                    <button type="button" @click="startAction('link')"
                            class="w-full text-left rounded-xl ring-1 ring-brand/20 bg-white hover:bg-brand-muted/40 px-4 py-3">
                        <p class="text-sm font-bold text-brand">Same person → Link to this Partner</p>
                        <p class="text-xs text-gray-600 mt-0.5" x-text="currentMatch().link_preview"></p>
                    </button>

                    <button type="button" @click="startAction('keep_separate')"
                            class="w-full text-left rounded-xl ring-1 ring-gray-200 bg-white hover:bg-gray-50 px-4 py-3">
                        <p class="text-sm font-bold text-gray-900">Different people → Keep separate</p>
                        <p class="text-xs text-gray-600 mt-0.5">
                            Marks this collision as different identities for this application.
                            Does not bypass a genuine unique-field conflict (for example a shared login email).
                        </p>
                    </button>

                    <a :href="currentMatch().partner_url" target="_blank" rel="noopener"
                       class="block w-full text-left rounded-xl ring-1 ring-gray-200 bg-white hover:bg-gray-50 px-4 py-3">
                        <p class="text-sm font-bold text-gray-900">
                            Open <span x-text="currentMatch().existing?.name || 'Partner'"></span> →
                        </p>
                        <p class="text-xs text-gray-600 mt-0.5">
                            Investigate this specific Partner 360 record. Do not assume it is wrong — correct it there only if investigation shows its data is incorrect.
                        </p>
                    </a>

                    <button type="button" @click="pickRequestInfo()"
                            class="w-full text-left rounded-xl ring-1 ring-sky-200 bg-sky-50/60 hover:bg-sky-50 px-4 py-3">
                        <p class="text-sm font-bold text-sky-950">Request information →</p>
                        <p class="text-xs text-sky-900/80 mt-0.5">When the applicant’s identity or contact details need clarification — reopen Request information on this application.</p>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <div x-show="matchPhase === 'review'" x-cloak class="space-y-4">
        <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/15 p-4 text-sm text-brand space-y-2">
            <p class="font-bold" x-text="reviewTitle()"></p>
            <p class="text-brand/90 whitespace-pre-line" x-text="reviewMessage()"></p>
        </div>
        <label x-show="matchAction === 'link' && (currentMatch()?.conflict_fields || []).length" x-cloak
               class="flex items-start gap-2 text-sm text-rose-900 bg-rose-50 ring-1 ring-rose-200 rounded-xl px-3 py-2.5">
            <input type="checkbox" class="mt-1 rounded border-rose-300 text-brand focus:ring-brand" x-model="confirmDespiteConflicts">
            <span>I confirm these records belong to the same person despite conflicting identity fields.</span>
        </label>
        <div class="flex gap-2">
            <button type="button" @click="backToCompare()"
                    class="flex-1 rounded-xl ring-1 ring-gray-200 py-3 text-sm font-semibold text-gray-700"
                    :disabled="matchBusy">Back</button>
            <button type="button" @click="confirmMatch()"
                    class="flex-1 rounded-xl bg-brand hover:bg-brand-light text-white py-3 text-sm font-semibold disabled:opacity-50"
                    :disabled="matchBusy || (matchAction === 'link' && (currentMatch()?.conflict_fields || []).length && !confirmDespiteConflicts)"
                    x-text="matchBusy ? 'Saving…' : (matchAction === 'link' ? 'Confirm link' : 'Confirm keep separate')">
                Confirm
            </button>
        </div>
    </div>
</x-site.action-panel>
