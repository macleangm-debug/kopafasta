<x-admin.layout title="KYC Rules" heading="KYC Rules" subheading="Required documents & verification thresholds">
    @include('admin.settings._tabs', ['active' => 'kyc'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.kyc.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')

        @php
            $bools = [
                'require_nida'         => 'Require NIDA',
                'require_tin'          => 'Require TIN',
                'require_selfie'       => 'Require selfie',
                'require_address_proof'=> 'Require address proof',
                'require_income_proof' => 'Require income proof',
                'auto_approve_low_risk'=> 'Auto-approve low risk customers',
                'crb_check_required'   => 'CRB check required',
            ];
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach ($bools as $k => $lbl)
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                    <input type="hidden" name="{{ $k }}" value="0">
                    <input type="checkbox" name="{{ $k }}" value="1" @checked(!empty($values[$k])) class="size-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                    <span class="text-gray-800">{{ $lbl }}</span>
                </label>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="min_age" label="Minimum age" type="number" :value="$values['min_age'] ?? '18'" required />
            <x-admin.input name="max_age" label="Maximum age" type="number" :value="$values['max_age'] ?? '75'" required />
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save KYC rules</button>
        </div>
    </form>
</x-admin.layout>
