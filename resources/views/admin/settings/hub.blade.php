@php
    $groups = [
        'Organization' => [
            ['Company profile', 'admin.settings.company'],
            ['Authentication', 'admin.settings.auth-portal'],
            ['Branches', 'admin.branches.index'],
            ['Departments', 'admin.departments.index'],
            ['Users', 'admin.users.index'],
            ['Roles & permissions', 'admin.roles.index'],
            ['Locations', 'admin.settings.locations.index'],
            ['Countries', 'admin.settings.countries'],
        ],
        'Lending rules' => [
            ['Loan products', 'admin.loan-products.index'],
            ['Underwriting', 'admin.settings.underwriting'],
            ['Loan rules', 'admin.settings.loan-rules'],
            ['Offer settings', 'admin.settings.offer'],
            ['Credit policy', 'admin.settings.credit-policy'],
            ['Approval limits', 'admin.approval-limits.index'],
            ['Asset lending', 'admin.settings.asset-lending'],
        ],
        'Identity & compliance' => [
            ['KYC rules', 'admin.settings.kyc'],
            ['Identity verification', 'admin.settings.identity'],
            ['AML thresholds', 'admin.settings.aml'],
            ['CRB integration', 'admin.settings.crb'],
        ],
        'Legal' => [
            ['Contracts & clauses', 'admin.settings.legal'],
            ['Signatories', 'admin.settings.signatories.index'],
            ['Document templates', 'admin.document-templates.index'],
        ],
        'Finance' => [
            ['Finance defaults', 'admin.settings.finance'],
            ['Payment accounts', 'admin.settings.payment-accounts'],
            ['Charges & fees', 'admin.charges-fees.index'],
        ],
        'Growth' => [
            ['Membership', 'admin.settings.membership'],
            ['Referrals', 'admin.settings.referrals'],
            ['Engagement', 'admin.settings.engagement'],
            ['Affiliates', 'admin.settings.affiliates'],
            ['Campaigns', 'admin.promotions.index'],
        ],
        'Partners & recovery' => [
            ['Partners hub', 'admin.partners.index'],
            ['Partner tasks', 'admin.partners.tasks'],
            ['Enrollment applications', 'admin.partner-applications.index'],
            ['Recovery policy', 'admin.settings.recovery'],
        ],
        'Communications' => [
            ['SMS / Email', 'admin.settings.gateways'],
            ['Notification templates', 'admin.notification-templates.index'],
            ['Chatbot', 'admin.settings.chatbot'],
        ],
    ];
@endphp

<x-admin.layout title="Settings hub" heading="Settings hub" subheading="One place for configuration — grouped by how teams work">
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-brand to-brand-light px-5 py-5 text-white ring-1 ring-brand/20">
        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ brand_name() }}</p>
        <p class="text-lg font-semibold mt-1">Configure once, reuse everywhere</p>
        <p class="text-sm text-white/75 mt-1">Lending rules, identity, finance, growth, and partner settings — without duplicate menus.</p>
    </div>
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach ($groups as $groupName => $links)
            <section class="bg-white rounded-xl ring-1 ring-brand/10 p-5">
                <h2 class="text-sm font-bold uppercase tracking-widest text-brand mb-4">{{ $groupName }}</h2>
                <ul class="space-y-2">
                    @foreach ($links as [$label, $route])
                        <li>
                            <a href="{{ route($route) }}" class="text-sm font-semibold text-brand hover:underline">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-admin.layout>
