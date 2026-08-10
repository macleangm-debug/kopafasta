<x-admin.layout title="Partner membership" heading="Partner membership" subheading="Yearly membership for non-affiliate partners — renewals and optional activation fees">
    @include('admin.settings._tabs', ['active' => 'partners'])

<form method="POST" action="{{ route('admin.settings.partners.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Defaults</h3>
                <p class="text-xs text-gray-500 mt-1">Affiliates use Affiliate settings (TZS 50,000 / year). Other partners default to one year after activation and can request renewal when expiry approaches.</p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                <input type="hidden" name="membership_enabled" value="0">
                <input type="checkbox" name="membership_enabled" value="1"
                       @checked((bool) ($values['enabled'] ?? true))
                       class="rounded border-gray-300 text-brand">
                Enable partner membership tracking
            </label>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-admin.input name="default_fee_amount" label="Default fee (TZS)" type="number" step="1000" min="0"
                               :value="$values['default_fee_amount'] ?? 0" money />
                <x-admin.input name="default_duration_days" label="Duration (days)" type="number" min="1"
                               :value="$values['default_duration_days'] ?? 365" />
                <x-admin.input name="grace_period_days" label="Grace after expiry (days)" type="number" min="0"
                               :value="$values['grace_period_days'] ?? 14" />
                <x-admin.input name="notify_days_before_expiry" label="Notify before expiry (days)" type="number" min="1"
                               :value="$values['notify_days_before_expiry'] ?? 30" />
            </div>
        </div>

        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Who must pay</h3>
                <p class="text-xs text-gray-500 mt-1">Tick partner types that must pay a membership fee (e.g. individual valuers). Unticked types still get a one-year membership window after activation and can request renewal.</p>
            </div>
            <div class="space-y-3">
                @foreach ($roles as $key => $label)
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-xl ring-1 ring-gray-100 px-4 py-3">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-800 sm:w-48 shrink-0">
                            <input type="hidden" name="categories_requiring_payment[{{ $key }}]" value="0">
                            <input type="checkbox" name="categories_requiring_payment[{{ $key }}]" value="1"
                                   @checked((bool) (($values['categories_requiring_payment'][$key] ?? false)))
                                   class="rounded border-gray-300 text-brand">
                            {{ $label }}
                        </label>
                        <div class="flex-1">
                            <x-admin.input :name="'category_fees['.$key.']'" label="Fee (TZS)" type="number" step="1000" min="0"
                                           :value="$values['category_fees'][$key] ?? ($values['default_fee_amount'] ?? 0)" money />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
            Save partner membership settings
        </button>
    </form>
</x-admin.layout>
