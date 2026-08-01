<x-admin.layout title="Credit Policy" heading="Credit Policy" subheading="Country rules, affordability limits, and rejection reasons">
    @include('admin.settings._tabs', ['active' => 'credit-policy'])
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.credit-policy.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Country configuration</h3>
            <p class="text-xs text-gray-500 mb-4">Per-country language, currency, ID format, and grace rules are managed in <a href="{{ route('admin.settings.countries') }}" class="text-amber-700 font-semibold hover:underline">Country Settings</a>. Credit ratios below apply to the default country.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <x-admin.input name="default_code" label="Default country code" :value="$country['code'] ?? 'TZ'" required maxlength="2" />
                <x-admin.input name="repayment_ratio_pct" label="Maximum repayment ratio (%)" type="number" step="0.01" :value="$country['repayment_ratio_pct'] ?? 33.33" required />
                <x-admin.input name="crb_freshness_days" label="CRB freshness (days)" type="number" :value="$country['crb_freshness_days'] ?? 90" required />
                <x-admin.input name="kyc_freshness_days" label="KYC freshness (days)" type="number" :value="$country['kyc_freshness_days'] ?? 90" required />
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-2">
                    <input type="hidden" name="guarantor_required" value="0">
                    <input type="checkbox" name="guarantor_required" value="1" @checked($country['guarantor_required'] ?? true) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                    <span class="text-gray-800">Guarantor required (country default)</span>
                </label>
            </div>
            <p class="mt-3 text-xs text-gray-500">
                Example: monthly income 900,000 TZS × 33.33% = maximum repayment 300,000 TZS.
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Loan rejection reasons</h3>
            <p class="text-xs text-gray-500 mb-4">Underwriters must select from these standardized reasons. Uncheck to disable a reason.</p>
            <div class="space-y-5">
                @foreach ($rejectionReasons as $category => $reasons)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ $category }}</p>
                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach ($reasons as $reason)
                                <label class="flex items-start gap-2 text-sm text-gray-700 bg-gray-50 rounded-lg px-3 py-2 ring-1 ring-gray-100">
                                    <input type="checkbox" name="enabled_reasons[]" value="{{ $reason['code'] }}"
                                           @checked(in_array($reason['code'], $enabledCodes, true))
                                           class="mt-0.5 rounded border-gray-300 text-brand">
                                    <span>{{ $reason['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-6 py-2.5 rounded-lg">
                Save credit policy
            </button>
        </div>
    </form>
</x-admin.layout>
