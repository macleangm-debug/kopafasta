<x-admin.layout title="Underwriting" heading="Underwriting settings" subheading="Guarantor gates, document SLAs, and default interest tier generation">
    @include('admin.settings._tabs', ['active' => 'underwriting'])
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.underwriting.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Guarantor workflow</h3>
            <p class="text-xs text-gray-500 mb-4">Controls when applications enter underwriting and how long guarantor invitations stay valid.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="guarantor_invitation_expiry_days" label="Guarantor invitation expiry (days)" type="number"
                               :value="$values['guarantor_invitation_expiry_days'] ?? 14" required />
                <x-admin.input name="stage_sla_days" label="Underwriting stage SLA reminder (days)" type="number"
                               :value="$values['stage_sla_days'] ?? 5" required />
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-2">
                    <input type="hidden" name="hold_applications_until_guarantor_approved" value="0">
                    <input type="checkbox" name="hold_applications_until_guarantor_approved" value="1"
                           @checked(! isset($values['hold_applications_until_guarantor_approved']) || ! empty($values['hold_applications_until_guarantor_approved']))
                           class="size-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                    <span class="text-gray-800">Hold new applications in “awaiting guarantor” until guarantor approves (guarantor lockout)</span>
                </label>
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-2">
                    <input type="hidden" name="block_acknowledge_without_guarantor" value="0">
                    <input type="checkbox" name="block_acknowledge_without_guarantor" value="1"
                           @checked(! isset($values['block_acknowledge_without_guarantor']) || ! empty($values['block_acknowledge_without_guarantor']))
                           class="size-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                    <span class="text-gray-800">Block underwriting acknowledgement while guarantor is incomplete</span>
                </label>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Document requests</h3>
            <p class="text-xs text-gray-500 mb-4">Default due date when underwriting requests additional documents without specifying a date.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="document_request_default_due_days" label="Default due date (days from request)" type="number"
                               :value="$values['document_request_default_due_days'] ?? 7" required />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Interest tier defaults</h3>
            <p class="text-xs text-gray-500 mb-4">
                Used when auto-generating amount bands on loan products. Per-product overrides remain on each product edit page.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="default_rate_tier_count" label="Default number of amount bands" type="number"
                               :value="$values['default_rate_tier_count'] ?? 4" required />
                <x-admin.input name="default_rate_discount_fraction" label="Rate discount on largest band (0–0.85)" type="number" step="0.01"
                               :value="$values['default_rate_discount_fraction'] ?? 0.30" required />
            </div>
            <p class="mt-3 text-xs text-amber-800/90 rounded-lg bg-amber-50 ring-1 ring-amber-100 px-3 py-2">
                Example: base rate 19% with discount 0.30 → lowest band ≈ 13.3% monthly.
            </p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">
                Save underwriting settings
            </button>
        </div>
    </form>
</x-admin.layout>
