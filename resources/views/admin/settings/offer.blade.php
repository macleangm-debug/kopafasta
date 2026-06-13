<x-admin.layout title="Offer Settings" heading="Offer settings" subheading="Pilot acceptance flow and repayment commencement defaults">
    @include('admin.settings._tabs', ['active' => 'offer'])
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.offer.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Offer acceptance</h3>
            <p class="text-xs text-gray-500 mb-4">
                For the pilot, keep acceptance codes disabled until bulk SMS is integrated.
                When disabled, borrowers accept with one click — no OTP required.
            </p>
            <div class="space-y-3">
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                    <input type="hidden" name="require_offer_acceptance_code" value="0">
                    <input type="checkbox" name="require_offer_acceptance_code" value="1"
                           @checked(! empty($values['require_offer_acceptance_code']))
                           class="size-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                    <span class="text-gray-800">Require offer acceptance code (SMS OTP)</span>
                </label>
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                    <input type="hidden" name="require_contract_acceptance_code" value="0">
                    <input type="checkbox" name="require_contract_acceptance_code" value="1"
                           @checked(! empty($values['require_contract_acceptance_code']))
                           class="size-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                    <span class="text-gray-800">Require contract acceptance code (SMS OTP)</span>
                </label>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Repayment commencement</h3>
            <p class="text-xs text-gray-500 mb-4">
                Used on pre-disbursement contracts and when generating the actual schedule after disbursement.
                First instalment falls due this many days after the disbursement date.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="repayment_commencement_days" label="Days after disbursement" type="number" min="0" max="90"
                               :value="$values['repayment_commencement_days'] ?? 7" required />
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">
                Save offer settings
            </button>
        </div>
    </form>
</x-admin.layout>
