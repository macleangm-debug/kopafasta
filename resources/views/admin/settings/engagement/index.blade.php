<x-admin.layout title="Engagement" heading="Member engagement" subheading="Gamification, rewards, trust score, and loyalty">
    @include('admin.settings.engagement._nav', ['active' => 'index'])

    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ([
            ['Referral levels', 'admin.settings.engagement.referral-levels', 'Tiered referral rewards and milestones'],
            ['Trust score', 'admin.settings.engagement.trust-score', 'Weights and unlock benefits'],
            ['Community milestones', 'admin.settings.engagement.milestones', 'Help N people join campaigns'],
            ['Repayment streak', 'admin.settings.engagement.repayment-streak', 'On-time repayment rewards'],
            ['Profile strength', 'admin.settings.engagement.profile-strength', 'Bronze → Verified tiers'],
            ['Loyalty points', 'admin.settings.engagement.loyalty-points', 'Earn and redeem actions'],
            ['Underwriting boosts', 'admin.settings.engagement.underwriting', 'Limit, rate, and SLA multipliers'],
            ['Notifications', 'admin.settings.engagement.notifications', 'Categories and leaderboard'],
            ['Profile builder', 'admin.profile-sections.index', 'Configurable profile sections'],
        ] as [$title, $route, $desc])
            <a href="{{ route($route) }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-5 hover:ring-amber-300 transition block">
                <h2 class="font-semibold text-gray-900">{{ $title }}</h2>
                <p class="text-sm text-gray-500 mt-2">{{ $desc }}</p>
            </a>
        @endforeach
    </div>
</x-admin.layout>
