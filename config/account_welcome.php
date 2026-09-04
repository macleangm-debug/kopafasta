<?php

return [
    'enabled' => true,
    'audiences' => [
        'borrower' => [
            ['title' => 'account_welcome.borrower.welcome_title', 'body' => 'account_welcome.borrower.welcome_body', 'illustration' => 'welcome'],
            ['title' => 'account_welcome.borrower.products_title', 'body' => 'account_welcome.borrower.products_body', 'illustration' => 'wallet'],
            ['title' => 'account_welcome.borrower.points_title', 'body' => 'account_welcome.borrower.points_body', 'illustration' => 'rewards', 'variant' => 'rewards'],
            ['title' => 'account_welcome.borrower.grade_title', 'body' => 'account_welcome.borrower.grade_body', 'illustration' => 'grade'],
            ['title' => 'account_welcome.borrower.plus_title', 'body' => 'account_welcome.borrower.plus_body', 'illustration' => 'plus'],
            ['title' => 'account_welcome.borrower.ready_title', 'body' => 'account_welcome.borrower.ready_body', 'illustration' => 'ready'],
        ],
        'affiliate' => [
            ['title' => 'account_welcome.affiliate.welcome_title', 'body' => 'account_welcome.affiliate.welcome_body', 'illustration' => 'welcome'],
            ['title' => 'account_welcome.affiliate.share_title', 'body' => 'account_welcome.affiliate.share_body', 'illustration' => 'share'],
            ['title' => 'account_welcome.affiliate.track_title', 'body' => 'account_welcome.affiliate.track_body', 'illustration' => 'plus'],
            ['title' => 'account_welcome.affiliate.earn_title', 'body' => 'account_welcome.affiliate.earn_body', 'illustration' => 'rewards'],
            ['title' => 'account_welcome.affiliate.ready_title', 'body' => 'account_welcome.affiliate.ready_body', 'illustration' => 'ready'],
        ],
        'valuer' => [
            ['title' => 'account_welcome.valuer.welcome_title', 'body' => 'account_welcome.valuer.welcome_body', 'illustration' => 'welcome'],
            ['title' => 'account_welcome.valuer.jobs_title', 'body' => 'account_welcome.valuer.jobs_body', 'illustration' => 'cases'],
            ['title' => 'account_welcome.valuer.inspect_title', 'body' => 'account_welcome.valuer.inspect_body', 'illustration' => 'inspect'],
            ['title' => 'account_welcome.valuer.submit_title', 'body' => 'account_welcome.valuer.submit_body', 'illustration' => 'rewards'],
            ['title' => 'account_welcome.valuer.ready_title', 'body' => 'account_welcome.valuer.ready_body', 'illustration' => 'ready'],
        ],
        'gps_installer' => [
            ['title' => 'account_welcome.gps.welcome_title', 'body' => 'account_welcome.gps.welcome_body', 'illustration' => 'welcome'],
            ['title' => 'account_welcome.gps.jobs_title', 'body' => 'account_welcome.gps.jobs_body', 'illustration' => 'gps'],
            ['title' => 'account_welcome.gps.evidence_title', 'body' => 'account_welcome.gps.evidence_body', 'illustration' => 'inspect'],
            ['title' => 'account_welcome.gps.ready_title', 'body' => 'account_welcome.gps.ready_body', 'illustration' => 'ready'],
        ],
        'insurance' => [
            ['title' => 'account_welcome.insurance.welcome_title', 'body' => 'account_welcome.insurance.welcome_body', 'illustration' => 'welcome'],
            ['title' => 'account_welcome.insurance.requests_title', 'body' => 'account_welcome.insurance.requests_body', 'illustration' => 'insurance'],
            ['title' => 'account_welcome.insurance.complete_title', 'body' => 'account_welcome.insurance.complete_body', 'illustration' => 'inspect'],
            ['title' => 'account_welcome.insurance.ready_title', 'body' => 'account_welcome.insurance.ready_body', 'illustration' => 'ready'],
        ],
        'recovery' => [
            ['title' => 'account_welcome.recovery.welcome_title', 'body' => 'account_welcome.recovery.welcome_body', 'illustration' => 'welcome'],
            ['title' => 'account_welcome.recovery.cases_title', 'body' => 'account_welcome.recovery.cases_body', 'illustration' => 'cases'],
            ['title' => 'account_welcome.recovery.action_title', 'body' => 'account_welcome.recovery.action_body', 'illustration' => 'inspect'],
            ['title' => 'account_welcome.recovery.ready_title', 'body' => 'account_welcome.recovery.ready_body', 'illustration' => 'ready'],
        ],
    ],
];
