<x-admin.layout title="Partner membership" heading="Partner membership" subheading="Yearly membership for non-affiliate partners — renewals and optional activation fees">
    @include('admin.settings._tabs', ['active' => 'partners'])

    <x-admin.settings-editor
        action="{{ route('admin.settings.partners.save') }}"
        submit-label="Save partner membership settings"
        :tabs="[
            'defaults' => 'Defaults',
            'who' => 'Who must pay',
        ]"
    >
        <x-admin.settings-panel id="defaults">
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Defaults</h3>
                    <p class="text-xs text-gray-500 mt-1">The amounts saved here are what the partner portal charges. Affiliates use Affiliate settings (TZS 25,000 individual / TZS 50,000 company) — that fee is separate. Partner membership does not accept promo or affiliate codes.</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="membership_enabled" value="0">
                    <input type="checkbox" name="membership_enabled" value="1"
                           @checked((bool) ($values['enabled'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Enable partner membership tracking
                </label>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <x-admin.input name="default_fee_amount" label="Default fee (TZS)" type="number" step="100" min="0"
                                   :value="$values['default_fee_amount'] ?? 0" money />
                    <x-admin.input name="default_duration_days" label="Duration (days)" type="number" min="1"
                                   :value="$values['default_duration_days'] ?? 365" />
                    <x-admin.input name="grace_period_days" label="Grace after expiry (days)" type="number" min="0"
                                   :value="$values['grace_period_days'] ?? 14" />
                    <x-admin.input name="notify_days_before_expiry" label="Notify before expiry (days)" type="number" min="1"
                                   :value="$values['notify_days_before_expiry'] ?? 30" />
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="who">
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Who must pay</h3>
                    <p class="text-xs text-gray-500 mt-1">Tick a membership fee for each partner type — that amount is charged on the partner pay screen. Valuers split individual vs company (TZS 1,500 / TZS 2,000 by default). Affiliates use Affiliate settings, not this list. Promo codes never apply to partner membership. Unticked types still get a one-year membership window after they finish their portal profile.</p>
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
                                @php
                                    $rawFee = $values['category_fees'][$key] ?? null;
                                    $fallbackFee = $values['default_fee_amount'] ?? 0;
                                    if (is_array($rawFee)) {
                                        $individualFee = $rawFee['individual'] ?? $fallbackFee;
                                        $companyFee = $rawFee['company'] ?? $individualFee;
                                    } else {
                                        $individualFee = $rawFee ?? $fallbackFee;
                                        $companyFee = $individualFee;
                                    }
                                    $splitApplicant = \App\Services\PartnerMembershipService::roleSplitsByApplicant($key);
                                @endphp
                                @if ($splitApplicant)
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <x-admin.input :name="'category_fees['.$key.'][individual]'" label="Individual fee (TZS)" type="number" step="100" min="0"
                                                       :value="$individualFee" money />
                                        <x-admin.input :name="'category_fees['.$key.'][company]'" label="Company fee (TZS)" type="number" step="100" min="0"
                                                       :value="$companyFee" money />
                                    </div>
                                @else
                                    <x-admin.input :name="'category_fees['.$key.']'" label="Fee (TZS)" type="number" step="100" min="0"
                                                   :value="is_array($rawFee) ? ($companyFee ?? $fallbackFee) : ($rawFee ?? $fallbackFee)" money />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
