<x-admin.layout title="Legal" heading="Legal settings" subheading="Signatory, company stamp, and contract clause defaults">
    @include('admin.settings._tabs', ['active' => 'legal'])
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.legal.save') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Authorised signatory</h3>
            <p class="text-xs text-gray-500 mb-4">Appears on offer letters and loan contracts as the company representative.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="signatory_name" label="Signatory name" :value="$values['signatory_name'] ?? ''" placeholder="John Doe" />
                <x-admin.input name="signatory_title" label="Position / title" :value="$values['signatory_title'] ?? ''" placeholder="Chief Executive Officer" />
                <x-admin.input name="signatory_email" label="Email" type="email" :value="$values['signatory_email'] ?? ''" placeholder="ceo@company.co.tz" />
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Signature image</label>
                    @if (! empty($values['signature_path']))
                        <img src="{{ asset('storage/'.$values['signature_path']) }}" alt="Company signature" class="h-20 mb-3 object-contain">
                    @endif
                    <input type="file" name="signature_image" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-gray-600">
                    <p class="text-xs text-gray-500 mt-1">PNG or JPG on transparent or white background. Used on PDF contracts.</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Company stamp</h3>
            <p class="text-xs text-gray-500 mb-4">Upload your official company stamp (transparent PNG recommended).</p>
            <div>
                @if (! empty($values['stamp_path']))
                    <img src="{{ asset('storage/'.$values['stamp_path']) }}" alt="Company stamp" class="h-24 mb-3 object-contain">
                @endif
                <input type="file" name="stamp_image" accept="image/png,image/webp" class="block w-full text-sm text-gray-600">
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Offer letter</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="offer_validity_days" label="Offer validity (days)" type="number" min="1" max="90"
                               :value="$values['offer_validity_days'] ?? 14" required />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Contract sections</h3>
            <p class="text-xs text-gray-500 mb-4">
                Enable or disable sections included in loan contract PDFs. Manage signatories under
                <a href="{{ route('admin.settings.signatories.index') }}" class="text-amber-700 hover:underline">Signatories</a>.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($sectionLabels as $key => $label)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="contract_sections[{{ $key }}]" value="1"
                               @checked($contractSections[$key] ?? true)
                               class="rounded border-gray-300 text-amber-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Contract clauses</h3>
            <p class="text-xs text-gray-500 mb-4">
                Penalty rate, grace period, and cap are taken from
                <a href="{{ route('admin.settings.loan-rules') }}" class="text-amber-700 hover:underline">Loan Rules</a>.
                Configure display text for fees and recovery below — all contract PDFs pull these dynamically.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="late_fee_amount" label="Late fee (TZS)" type="number" step="1" min="0"
                               :value="$values['late_fee_amount'] ?? 2000" required />
                <x-admin.input name="jurisdiction" label="Jurisdiction" :value="$values['jurisdiction'] ?? 'United Republic of Tanzania'" required />
                <div class="md:col-span-2">
                    <x-admin.input name="collection_fee_text" label="Collection charge" :value="$values['collection_fee_text'] ?? 'Actual cost incurred'" />
                </div>
                <div class="md:col-span-2">
                    <x-admin.input name="legal_recovery_text" label="Legal recovery" :value="$values['legal_recovery_text'] ?? 'Borrower responsible for all legal recovery costs'" />
                </div>
                <div class="md:col-span-2">
                    <x-admin.textarea name="default_clause" label="Default clause" rows="2"
                                      :value="$values['default_clause'] ?? 'Failure to pay any instalment by the due date constitutes default after the grace period.'" />
                </div>
                <div class="md:col-span-2">
                    <x-admin.textarea name="collection_clause" label="Collection clause" rows="2"
                                      :value="$values['collection_clause'] ?? 'The lender may contact the borrower by phone, SMS, email, or in person to recover overdue amounts.'" />
                </div>
                <div class="md:col-span-2">
                    <x-admin.textarea name="recovery_clause" label="Recovery clause" rows="2"
                                      :value="$values['recovery_clause'] ?? 'Persistent default may result in legal recovery action and reporting to credit reference bureaus.'" />
                </div>
                <div class="md:col-span-2">
                    <x-admin.textarea name="penalty_clause" label="Penalty clause" rows="2"
                                      :value="$values['penalty_clause'] ?? 'Penalty interest and late fees apply as stated in the schedule of charges.'" />
                </div>
                <div class="md:col-span-2">
                    <x-admin.textarea name="legal_cost_clause" label="Legal costs clause" rows="2"
                                      :value="$values['legal_cost_clause'] ?? 'The borrower shall bear all reasonable legal costs incurred in recovering overdue amounts.'" />
                </div>
                <div class="md:col-span-2">
                    <x-admin.textarea name="guarantor_clause" label="Guarantor liability clause" rows="2"
                                      :value="$values['guarantor_clause'] ?? 'Where a guarantor has signed, they become jointly and severally liable for repayment.'" />
                </div>
                <div class="md:col-span-2">
                    <x-admin.textarea name="asset_recovery_clause" label="Asset recovery clause" rows="2"
                                      :value="$values['asset_recovery_clause'] ?? 'The lender may recover financed assets or collateral in accordance with applicable law and the asset lending terms.'" />
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">
                Save legal settings
            </button>
        </div>
    </form>
</x-admin.layout>
