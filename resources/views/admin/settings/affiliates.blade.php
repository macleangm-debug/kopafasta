<x-admin.layout title="Affiliate Settings" heading="Affiliate Settings" subheading="Promo code rules, defaults, and where affiliate discounts apply">
    @include('admin.settings._tabs', ['active' => 'affiliates'])

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.affiliates.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="code_prefix" label="Code prefix (fallback)" :value="$values['code_prefix'] ?? 'KPA'" required />
            <x-admin.input name="default_commission_percent" label="Default commission (%)" type="number" step="0.1" min="0" max="100"
                           :value="$values['default_commission_percent'] ?? 10" required />
            <x-admin.input name="default_registration_discount_percent" label="Default registration discount (%)" type="number" step="0.1" min="0" max="100"
                           :value="$values['default_registration_discount_percent'] ?? 10" required />
            <x-admin.input name="default_application_discount_percent" label="Default application discount (%)" type="number" step="0.1" min="0" max="100"
                           :value="$values['default_application_discount_percent'] ?? 10" required />
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Apply affiliate promo codes to</h3>
            <p class="text-xs text-gray-500 mb-4">Choose which fee types accept affiliate discounts and accrue commission.</p>
            @php
                $feeLabels = [
                    'registration_fee'  => 'Registration fee',
                    'application_fee'   => 'Application fee',
                    'post_approval_fee' => 'Post approval fee',
                    'interest'          => 'Interest',
                    'repayments'        => 'Repayments',
                ];
            @endphp
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach ($feeLabels as $key => $label)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                        <input type="hidden" name="applies_to[{{ $key }}]" value="0">
                        <input type="checkbox" name="applies_to[{{ $key }}]" value="1"
                               @checked((bool) ($values['applies_to'][$key] ?? false))
                               class="rounded border-gray-300 text-amber-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">
                Save affiliate settings
            </button>
        </div>
    </form>
</x-admin.layout>
