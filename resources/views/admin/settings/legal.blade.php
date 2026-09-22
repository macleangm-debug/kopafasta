<x-admin.layout title="Contracts & Clauses" heading="Contracts & Clauses" subheading="What makes up our contracts — sections and clauses">
    @include('admin.settings._tabs', ['active' => 'legal'])

    <div class="mb-5 grid sm:grid-cols-3 gap-3">
        <a href="{{ route('admin.settings.signatories.index') }}" class="rounded-xl bg-white ring-1 ring-brand/15 px-4 py-3 hover:bg-brand-muted/30">
            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">1 · Who authorizes</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">Signatories & Company Seal</p>
        </a>
        <a href="{{ route('admin.document-templates.index') }}" class="rounded-xl bg-white ring-1 ring-brand/15 px-4 py-3 hover:bg-brand-muted/30">
            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">2 · What we issue</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">Document Templates · Preview</p>
        </a>
        <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/20 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">3 · Contract makeup</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">You are here — Sections & clauses</p>
        </div>
    </div>

    <div class="mb-5 grid sm:grid-cols-2 gap-3 text-sm">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3">
            <p class="text-xs font-semibold text-gray-900">Company stamp</p>
            <p class="text-xs text-gray-500 mt-1">Configured under Signatories & Company Seal — not here.</p>
            <a href="{{ route('admin.settings.signatories.index') }}" class="inline-block mt-2 text-xs font-semibold text-brand hover:underline">Open stamp →</a>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3">
            <p class="text-xs font-semibold text-gray-900">Offer validity</p>
            <p class="text-xs text-gray-500 mt-1">Configured with Offer Letter under Document Templates.</p>
            <a href="{{ route('admin.document-templates.index') }}#offer-config" class="inline-block mt-2 text-xs font-semibold text-brand hover:underline">Open offer config →</a>
        </div>
    </div>

    @php
        $sectionClauseMap = [
            'definitions' => ['Jurisdiction framing'],
            'loan_terms' => [],
            'repayment_obligations' => [],
            'default_events' => ['Default clause'],
            'penalty_clauses' => ['Penalty clause'],
            'recovery_clauses' => ['Collection clause', 'Recovery clause', 'Collection charge', 'Legal recovery'],
            'guarantor_obligations' => ['Guarantor liability clause'],
            'legal_costs' => ['Legal costs clause'],
            'jurisdiction' => ['Jurisdiction'],
            'data_privacy' => [],
            'signatures' => [],
        ];
        $clauseUsage = [
            'jurisdiction' => ['Loan Contract → Jurisdiction'],
            'collection_fee_text' => ['Loan Contract → Recovery'],
            'legal_recovery_text' => ['Loan Contract → Legal costs'],
            'default_clause' => ['Loan Contract → Default events', 'Asset-Backed Contract → Default & Enforcement'],
            'collection_clause' => ['Loan Contract → Recovery'],
            'recovery_clause' => ['Loan Contract → Recovery'],
            'penalty_clause' => ['Loan Contract → Penalty'],
            'legal_cost_clause' => ['Loan Contract → Legal costs'],
            'guarantor_clause' => ['Loan Contract → Guarantor obligations'],
            'asset_recovery_clause' => ['Asset-Backed Contract → Default & Enforcement'],
        ];
    @endphp

    <x-admin.settings-editor
        action="{{ route('admin.settings.legal.save') }}"
        submit-label="Save contracts & clauses"
        enctype="multipart/form-data"
        :tabs="[
            'sections' => 'Sections',
            'clauses' => 'Clauses',
        ]"
    >
        <x-admin.settings-panel id="sections">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Contract sections</h3>
                        <p class="text-xs text-gray-500">
                            Ordered blocks in the Loan Contract. Enable/disable below. Preview the resulting contract inside Kopafasta.
                        </p>
                    </div>
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js(route('admin.document-templates.preview', 'loan_contract')), 'Loan Contract', 'pdf')"
                            class="inline-flex text-xs font-semibold rounded-lg bg-brand text-white px-3 py-2 hover:bg-brand-light">
                        Preview in document →
                    </button>
                </div>
                <div class="space-y-3">
                    @foreach ($sectionLabels as $key => $label)
                        <label class="flex flex-col gap-1.5 rounded-xl ring-1 ring-gray-100 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                            <span class="inline-flex items-center gap-2">
                                <input type="checkbox" name="contract_sections[{{ $key }}]" value="1"
                                       @checked($contractSections[$key] ?? true)
                                       class="rounded border-gray-300 text-brand">
                                <span class="font-semibold text-gray-900">{{ $label }}</span>
                            </span>
                            <span class="text-[11px] text-gray-500 pl-6">
                                Appears in:
                                <span class="text-gray-700">Individual Loan Contract · Group Loan Contract · Asset-Backed Contract</span>
                                (where the product profile includes this section)
                            </span>
                            @if (! empty($sectionClauseMap[$key]))
                                <span class="text-[11px] text-gray-500 pl-6">
                                    Clauses in this section:
                                    <span class="text-gray-700">{{ implode(' · ', $sectionClauseMap[$key]) }}</span>
                                </span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="clauses">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Contract clauses</h3>
                        <p class="text-xs text-gray-500">
                            Each clause shows where it is used. Penalty rate / grace / cap come from
                            <a href="{{ route('admin.settings.loan-rules') }}" class="text-brand hover:underline">Loan Rules</a>.
                        </p>
                    </div>
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js(route('admin.document-templates.preview', 'loan_contract')), 'Loan Contract', 'pdf')"
                            class="inline-flex text-xs font-semibold rounded-lg bg-white ring-1 ring-gray-200 px-3 py-2 text-gray-800 hover:bg-gray-50">
                        Preview in document →
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-5">
                    <div>
                        <x-admin.input name="jurisdiction" label="Jurisdiction" :value="$values['jurisdiction'] ?? 'United Republic of Tanzania'" required />
                        <p class="text-[11px] text-gray-500 mt-1">Used in: {{ implode(' · ', $clauseUsage['jurisdiction']) }}</p>
                    </div>
                    <div>
                        <x-admin.input name="collection_fee_text" label="Collection charge" :value="$values['collection_fee_text'] ?? 'Actual cost incurred'" />
                        <p class="text-[11px] text-gray-500 mt-1">Used in: {{ implode(' · ', $clauseUsage['collection_fee_text']) }}</p>
                    </div>
                    <div>
                        <x-admin.input name="legal_recovery_text" label="Legal recovery" :value="$values['legal_recovery_text'] ?? 'Borrower responsible for all legal recovery costs'" />
                        <p class="text-[11px] text-gray-500 mt-1">Used in: {{ implode(' · ', $clauseUsage['legal_recovery_text']) }}</p>
                    </div>
                    @foreach ([
                        'default_clause' => ['Default clause', 'Failure to pay any instalment by the due date constitutes default after the grace period.'],
                        'collection_clause' => ['Collection clause', 'The lender may contact the borrower by phone, SMS, email, or in person to recover overdue amounts.'],
                        'recovery_clause' => ['Recovery clause', 'Persistent default may result in legal recovery action and reporting to credit reference bureaus.'],
                        'penalty_clause' => ['Penalty clause', 'Penalty interest applies as stated in the schedule of charges. Collection fees are added on top of amount owed when a recovery partner is assigned.'],
                        'legal_cost_clause' => ['Legal costs clause', 'The borrower shall bear all reasonable legal costs incurred in recovering overdue amounts.'],
                        'guarantor_clause' => ['Guarantor liability clause', 'Where a guarantor has signed, they become jointly and severally liable for repayment.'],
                        'asset_recovery_clause' => ['Asset recovery clause', 'The lender may recover financed assets or collateral in accordance with applicable law and the asset lending terms.'],
                    ] as $field => [$label, $default])
                        <div>
                            <x-admin.textarea :name="$field" :label="$label" rows="5"
                                              :value="$values[$field] ?? $default" />
                            <p class="text-[11px] text-gray-500 mt-1">Used in: {{ implode(' · ', $clauseUsage[$field] ?? []) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
