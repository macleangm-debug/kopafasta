<x-admin.layout title="KYC Rules" heading="KYC Rules" subheading="Required documents & verification thresholds">
    @include('admin.settings._tabs', ['active' => 'kyc'])
<form method="POST" action="{{ route('admin.settings.kyc.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')

        @php
            $bools = [
                'require_nida'         => 'Require NIDA',
                'require_tin'          => 'Require TIN',
                'require_selfie'       => 'Require selfie',
                'require_address_proof'=> 'Require residence verification letter',
                'require_income_proof' => 'Require income proof',
                'require_marriage_certificate' => 'Require marriage certificate (when married) — currently disabled in product until re-enabled',
                'auto_approve_low_risk'=> 'Auto-approve low risk customers',
                'crb_check_required'   => 'Pull CRB credit report after affordability / capacity pass (underwriting only)',
            ];
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach ($bools as $k => $lbl)
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                    <input type="hidden" name="{{ $k }}" value="0">
                    <input type="checkbox" name="{{ $k }}" value="1" @checked(!empty($values[$k])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                    <span class="text-gray-800">{{ $lbl }}</span>
                </label>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="min_age" label="Minimum age" type="number" :value="$values['min_age'] ?? '18'" required />
            <x-admin.input name="max_age" label="Maximum age" type="number" :value="$values['max_age'] ?? '75'" required />
            <x-admin.input name="crb_freshness_days" label="CRB freshness (days)" type="number" :value="$values['crb_freshness_days'] ?? '90'" required />
        </div>

        @php
            $sectionDays = $values['freshness_section_days'] ?? [];
            $freshnessSections = [
                'residence' => 'Residence information',
                'activity'  => 'Activity information',
                'documents' => 'Proof of income',
                'kin'       => 'Next of kin',
                'face'      => 'Face verification',
                'nida'      => 'NIDA verification',
            ];
        @endphp
        <div class="border-t border-gray-100 pt-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">KYC freshness (per section)</h3>
            <p class="text-xs text-gray-500 mb-4">Set days until refresh is required. Use <code class="text-[11px]">never</code> for sections that do not expire.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($freshnessSections as $key => $label)
                    <x-admin.input
                        name="freshness_section_days[{{ $key }}]"
                        :label="$label"
                        type="text"
                        :value="$sectionDays[$key] ?? (in_array($key, ['face', 'nida'], true) ? 'never' : ($key === 'kin' ? '365' : '90'))"
                        placeholder="90 or never"
                    />
                @endforeach
            </div>
        </div>

        <div class="border-t border-gray-100 pt-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Credit Bureau (D&amp;B Live Request)</h3>
            <p class="text-xs text-gray-500 mb-4">NIDA verification uses the Tanzania CRB SOAP API documented in the project <code class="text-[11px]">crb/</code> folder. Store the SOAP password in <code class="text-[11px]">CRB_PASSWORD</code> env.</p>
            <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 mb-4">
                <input type="hidden" name="crb_sandbox" value="0">
                <input type="checkbox" name="crb_sandbox" value="1" @checked(!empty($values['crb_sandbox'])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                <span class="text-gray-800">CRB sandbox / stub mode (no live bureau calls)</span>
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="crb_endpoint" label="CRB SOAP endpoint URL" :value="$values['crb_endpoint'] ?? ''" placeholder="https://..." />
                <x-admin.input name="crb_email" label="CRB user email (EmailID)" :value="$values['crb_email'] ?? ''" />
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save KYC rules</button>
        </div>
    </form>
</x-admin.layout>
