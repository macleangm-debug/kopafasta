@php
    $groups = [
        'Organization' => [
            ['Company', 'admin.settings.company'],
            ['Authentication', 'admin.settings.auth-portal'],
            ['Departments', 'admin.departments.index'],
            ['Users', 'admin.users.index'],
            ['Roles', 'admin.roles.index'],
        ],
        'Lending' => [
            ['Loan products', 'admin.loan-products.index'],
            ['Underwriting', 'admin.settings.underwriting'],
            ['Loan rules', 'admin.settings.loan-rules'],
            ['Offer settings', 'admin.settings.offer'],
            ['Asset lending', 'admin.settings.asset-lending'],
            ['Marketplace assets', 'admin.marketplace-assets.index'],
        ],
        'Legal' => [
            ['Contracts & clauses', 'admin.settings.legal'],
            ['Signatories', 'admin.settings.signatories.index'],
        ],
        'Finance' => [
            ['Finance defaults', 'admin.settings.finance'],
            ['Fees', 'admin.charges-fees.index'],
            ['Payment accounts', 'admin.settings.payment-accounts'],
        ],
        'Marketing' => [
            ['Membership', 'admin.settings.membership'],
            ['Referrals', 'admin.settings.referrals'],
            ['Affiliates', 'admin.settings.affiliates'],
            ['Campaigns', 'admin.promotions.index'],
        ],
        'Partners' => [
            ['Partners hub', 'admin.partners.index'],
            ['Partner tasks', 'admin.partners.tasks'],
            ['Affiliate applications', 'admin.partner-applications.index'],
        ],
        'Compliance' => [
            ['KYC rules', 'admin.settings.kyc'],
            ['Identity verification', 'admin.settings.identity'],
            ['Credit policy', 'admin.settings.credit-policy'],
            ['AML thresholds', 'admin.settings.aml'],
            ['Countries', 'admin.settings.countries'],
            ['Location master', 'admin.settings.locations.index'],
        ],
        'Integrations' => [
            ['SMS / Email', 'admin.settings.gateways'],
            ['Identity verification', 'admin.settings.identity'],
            ['CRB integration', 'admin.settings.crb'],
            ['Notification templates', 'admin.notification-templates.index'],
        ],
        'Recovery' => [
            ['Recovery policy', 'admin.settings.recovery'],
        ],
        'Reports' => [
            ['Portfolio', 'admin.reports.portfolio'],
            ['Disbursements', 'admin.reports.disbursements'],
            ['PAR', 'admin.reports.par'],
        ],
        'Capital' => [
            ['Capital funding', 'admin.capital-funding.index'],
            ['Lenders', 'admin.lenders.index'],
        ],
    ];
@endphp

<x-admin.layout title="Settings hub" heading="Settings hub" subheading="Organization, lending, partners, finance, and integrations">
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach ($groups as $groupName => $links)
            <section class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500 mb-4">{{ $groupName }}</h2>
                <ul class="space-y-2">
                    @foreach ($links as [$label, $route])
                        <li>
                            <a href="{{ route($route) }}" class="text-sm font-semibold text-amber-700 hover:underline">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-admin.layout>
