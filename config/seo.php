<?php

return [
    /*
    | Production-only indexing. Local, testing, staging, and QA must stay noindex
    | even if Settings Hub default index is on.
    */
    'allow_indexing' => env('SEO_ALLOW_INDEXING', env('APP_ENV') === 'production'),

    'title_pattern' => '{page} — {site}',

    'indexable_routes' => [
        'site.home',
        'site.plus',
        'site.rewards',
        'site.products',
        'site.product',
        'site.how-it-works',
        'site.about',
        'site.about.founding',
        'site.about.trust',
        'site.about.impact',
        'site.about.roadmap',
        'site.faq',
        'site.legal',
        'site.legal.terms',
        'site.legal.privacy',
        'site.legal.aml',
        'site.legal.complaints',
        'site.legal.cookies',
        'site.support',
        'site.invest',
        'site.capital-partners',
        'site.affiliate',
        'site.partners',
        'site.marketplace',
        'site.marketplace.show',
        'site.learn',
        'site.learn.category',
        'site.learn.show',
        'site.card.verify',
        'site.affiliate.verify.index',
    ],

    'private_path_prefixes' => [
        '/login',
        '/register',
        '/forgot-pin',
        '/staff-login',
        '/borrower',
        '/apply',
        '/partner/',
        '/investor',
        '/admin',
        '/staff',
        '/auth/two-factor',
        '/guarantor-request',
        '/group-member-invite',
        '/g/',
        '/verify/member',
        '/v/',
        '/partners/apply',
        '/partners/track',
        '/become-affiliate',
        '/waitlist',
    ],

    'tracking_query_keys' => [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'gclid',
        'fbclid',
        'msclkid',
        'ref',
    ],
];
