@props(['active' => 'company'])
@php
    $groups = [
        'Organization' => [
            'company'    => ['Company', 'admin.settings.company'],
            'departments'=> ['Departments', 'admin.departments.index'],
            'users'      => ['Users', 'admin.users.index'],
        ],
        'Legal' => [
            'legal'       => ['Contracts & clauses', 'admin.settings.legal'],
            'signatories' => ['Signatories', 'admin.settings.signatories.index'],
        ],
        'Lending' => [
            'loan-products' => ['Loan products', 'admin.settings.loan-products'],
            'underwriting'  => ['Underwriting', 'admin.settings.underwriting'],
            'loan-rules'    => ['Loan rules', 'admin.settings.loan-rules'],
            'offer'         => ['Offer settings', 'admin.settings.offer'],
            'asset-lending' => ['Asset lending', 'admin.settings.asset-lending'],
        ],
        'Recovery' => [
            'recovery' => ['Recovery policy', 'admin.settings.recovery'],
        ],
        'Finance' => [
            'finance' => ['Finance defaults', 'admin.settings.finance'],
            'fees'    => ['Fees', 'admin.charges-fees.index'],
        ],
        'Marketing' => [
            'membership' => ['Membership', 'admin.settings.membership'],
            'referrals'  => ['Referrals', 'admin.settings.referrals'],
            'affiliates' => ['Affiliates', 'admin.settings.affiliates'],
        ],
        'Compliance' => [
            'kyc'           => ['KYC rules', 'admin.settings.kyc'],
            'identity'      => ['Identity verification', 'admin.settings.identity'],
            'credit-policy' => ['Credit policy', 'admin.settings.credit-policy'],
            'aml'           => ['AML thresholds', 'admin.settings.aml'],
            'countries'     => ['Countries', 'admin.settings.countries'],
        ],
        'Integrations' => [
            'gateways' => ['SMS / Email', 'admin.settings.gateways'],
        ],
    ];

    $activeGroup = collect($groups)->search(fn ($tabs) => array_key_exists($active, $tabs)) ?: 'Organization';
@endphp

<div class="mb-6 space-y-3" x-data="{ group: @js($activeGroup) }">
    <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-3">
        @foreach (array_keys($groups) as $groupName)
            <button type="button"
                    @click="group = @js($groupName)"
                    :class="group === @js($groupName) ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'"
                    class="px-3 py-1.5 rounded-md text-sm font-medium transition">
                {{ $groupName }}
            </button>
        @endforeach
    </div>

    @foreach ($groups as $groupName => $tabs)
        <nav x-show="group === @js($groupName)" x-cloak class="flex flex-wrap gap-2">
            @foreach ($tabs as $key => [$label, $route])
                @php($isActive = $active === $key)
                <a href="{{ route($route) }}"
                   class="px-3 py-1.5 rounded-md text-sm font-medium transition {{ $isActive ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    @endforeach
</div>
