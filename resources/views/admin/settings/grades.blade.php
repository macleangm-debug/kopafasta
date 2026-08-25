<x-admin.layout title="Grades & Trust" heading="Customer grades & trust" subheading="Source of Truth for Bronze–Platinum. Saving creates a new rule version.">
    @include('admin.settings._tabs', ['active' => 'grades'])

    <p class="mb-4 text-sm text-gray-600">Current rule version: <strong>{{ $version ?? 'defaults' }}</strong></p>

    @if (!empty($backtest))
        <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach ($backtest as $grade => $count)
                <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ strtoupper($grade) }}</p>
                    <p class="text-2xl font-semibold">{{ $count }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <x-admin.settings-editor
        action="{{ route('admin.settings.grades.save') }}"
        submit-label="Save new rule version"
    >
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 grid grid-cols-2 md:grid-cols-3 gap-4">
            <x-admin.input name="weight_repayment" label="Repayment weight" type="number" :value="$rules['weights']['repayment'] ?? 35" />
            <x-admin.input name="weight_handled_credit" label="Handled credit weight" type="number" :value="$rules['weights']['handled_credit'] ?? 20" />
            <x-admin.input name="weight_relationship" label="Relationship weight" type="number" :value="$rules['weights']['relationship'] ?? 15" />
            <x-admin.input name="weight_current_position" label="Current position weight" type="number" :value="$rules['weights']['current_position'] ?? 15" />
            <x-admin.input name="weight_stability" label="Stability weight" type="number" :value="$rules['weights']['stability'] ?? 10" />
            <x-admin.input name="weight_verification" label="Verification weight" type="number" :value="$rules['weights']['verification'] ?? 5" />
            <x-admin.input name="grace_silver" label="Silver grace days" type="number" :value="$rules['grace_days']['silver'] ?? 14" />
            <x-admin.input name="grace_gold" label="Gold grace days" type="number" :value="$rules['grace_days']['gold'] ?? 30" />
            <x-admin.input name="grace_platinum" label="Platinum grace days" type="number" :value="$rules['grace_days']['platinum'] ?? 45" />
            <x-admin.input name="min_qualifying_principal" label="Min qualifying principal (TZS)" type="number" :value="$rules['integrity']['min_qualifying_principal'] ?? 100000" />
            <x-admin.input name="tz_bronze_access" label="TZ Bronze potential access" type="number" :value="$rules['country_bands']['TZ']['potential_access']['bronze'] ?? 500000" />
            <x-admin.input name="tz_silver_access" label="TZ Silver potential access" type="number" :value="$rules['country_bands']['TZ']['potential_access']['silver'] ?? 1500000" />
            <x-admin.input name="tz_gold_access" label="TZ Gold potential access" type="number" :value="$rules['country_bands']['TZ']['potential_access']['gold'] ?? 5000000" />
            <x-admin.input name="tz_platinum_access" label="TZ Platinum potential access" type="number" :value="$rules['country_bands']['TZ']['potential_access']['platinum'] ?? 15000000" />
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-5">
            <div>
                <h2 class="font-semibold text-gray-900">Grade benefits</h2>
                <p class="text-sm text-gray-600 mt-1">Shown to customers as entitlements. Never tell someone to borrow more to unlock a grade.</p>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                @foreach (['bronze', 'silver', 'gold', 'platinum'] as $grade)
                    @php $benefit = $rules['benefits'][$grade] ?? []; @endphp
                    <div class="rounded-xl ring-1 ring-gray-200 p-4 space-y-3">
                        <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ strtoupper($grade) }}</p>
                        <x-admin.select :name="'benefit_'.$grade.'_repeat'" label="Repeat journey" :options="['full' => 'Full application', 'confirm' => 'Confirm existing details', 'welcome_back' => 'Welcome back', 'prefill' => 'Prefill']" :value="$benefit['repeat_journey'] ?? 'full'" />
                        <x-admin.select :name="'benefit_'.$grade.'_priority'" label="Service priority" :options="['standard' => 'Standard', 'priority' => 'Priority', 'highest' => 'Highest']" :value="$benefit['priority'] ?? 'standard'" />
                        <x-admin.input :name="'benefit_'.$grade.'_offer_tier'" label="Partner offers tier" :value="$benefit['offer_tier'] ?? $grade" />
                        <x-admin.input :name="'benefit_'.$grade.'_rewards'" label="Reward benefits" :value="$benefit['rewards'] ?? ''" />
                        <x-admin.input :name="'benefit_'.$grade.'_exclusive'" label="Exclusive opportunities" :value="$benefit['exclusive'] ?? ''" />
                        <x-admin.input :name="'benefit_'.$grade.'_max_tenure'" label="Eligible duration cap (months)" type="number" :value="$benefit['max_tenure_months'] ?? ''" />
                    </div>
                @endforeach
            </div>
        </div>
    </x-admin.settings-editor>
</x-admin.layout>
