<x-admin.layout title="Engagement" heading="Member engagement" subheading="Gamification, rewards, trust score, and loyalty">
    @include('admin.settings.engagement._nav', ['active' => 'index'])

    @include('admin.settings.engagement._guide', [
        'title' => 'How engagement settings map to the borrower app',
        'summary' => 'These pages control the member rewards loop: earn trust and points → climb referral tiers → unlock better loan terms and fee discounts. Configure each area so the numbers match what members see on Profile, Rewards & referrals, and the loan amount step.',
        'borrowerSees' => [
            'Rewards & referrals hub — points balance, redeem catalog, referral link, streak, and leaderboard.',
            'Profile hub — completion % and profile-strength tier labels.',
            'Loan quote step — “Your member benefits” (limit + rate) from Underwriting boosts.',
            'Checkout — redeemed loyalty discounts on membership / application fees.',
        ],
        'tips' => [
            'Start with Referral levels and Trust score (inputs), then Underwriting boosts (loan outcomes), then Loyalty points (earn/redeem).',
            'After changing multipliers, open a test member’s apply quote to confirm the benefits card updates.',
            'Profile builder (linked below) controls which sections raise completion % — that feeds trust and profile strength.',
        ],
    ])

    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ([
            ['Referral levels', 'admin.settings.engagement.referral-levels', 'How many successful referrals unlock Bronze → Diamond, and milestone reward labels shown on the referral page.'],
            ['Trust score', 'admin.settings.engagement.trust-score', 'Weights for on-time payments, profile completion, referrals, account age, and successful loans. Drives stars and underwriting steps.'],
            ['Community milestones', 'admin.settings.engagement.milestones', 'Campaign-style “help N people join” goals and reward copy on the referrals tab.'],
            ['Repayment streak', 'admin.settings.engagement.repayment-streak', 'Bonus loyalty points after consecutive on-time instalments (shown on the streak tab).'],
            ['Profile strength', 'admin.settings.engagement.profile-strength', 'Labels for completion bands (Bronze → Verified) on the profile hub.'],
            ['Loyalty points', 'admin.settings.engagement.loyalty-points', 'Points earned per action and the redeem catalog used at membership / application fee checkout.'],
            ['Underwriting boosts', 'admin.settings.engagement.underwriting', 'How referral tier + trust score raise limit, cut rate, and speed review on the loan amount.'],
            ['Notifications', 'admin.settings.engagement.notifications', 'Which notification categories appear in the member inbox, plus referral leaderboard display rules.'],
            ['Profile builder', 'admin.profile-sections.index', 'Which profile sections exist and count toward completion % (feeds trust and apply gates).'],
        ] as [$title, $route, $desc])
            <a href="{{ route($route) }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-5 hover:ring-amber-300 transition block">
                <h2 class="font-semibold text-gray-900">{{ $title }}</h2>
                <p class="text-sm text-gray-500 mt-2">{{ $desc }}</p>
            </a>
        @endforeach
    </div>
</x-admin.layout>
