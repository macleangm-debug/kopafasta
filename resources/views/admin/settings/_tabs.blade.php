@props(['active' => 'company'])
@php
    $tabs = [
        'company'    => ['Company Profile', 'admin.settings.company'],
        'gateways'   => ['SMS / Email',     'admin.settings.gateways'],
        'kyc'        => ['KYC Rules',       'admin.settings.kyc'],
        'identity'   => ['Identity Verification', 'admin.settings.identity'],
        'loan-rules' => ['Loan Rules',      'admin.settings.loan-rules'],
        'offer'      => ['Offer Settings',  'admin.settings.offer'],
        'underwriting' => ['Underwriting',  'admin.settings.underwriting'],
        'legal'        => ['Legal',         'admin.settings.legal'],
        'signatories'  => ['Signatories',   'admin.settings.signatories.index'],
        'credit-policy' => ['Credit Policy', 'admin.settings.credit-policy'],
        'fees'       => ['Fee management',  'admin.charges-fees.index'],
        'loan-products' => ['Loan Products', 'admin.settings.loan-products'],
        'membership' => ['Membership',      'admin.settings.membership'],
        'referrals'  => ['Referrals',       'admin.settings.referrals'],
        'aml'        => ['AML Thresholds',  'admin.settings.aml'],
    ];
@endphp
<nav class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 pb-3">
    @foreach ($tabs as $key => [$label, $route])
        @php($isActive = $active === $key)
        <a href="{{ route($route) }}"
           class="px-3 py-1.5 rounded-md text-sm font-medium transition {{ $isActive ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
