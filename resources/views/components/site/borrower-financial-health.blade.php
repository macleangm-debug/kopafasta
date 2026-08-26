@props(['health'])

<section class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
    <div class="px-5 sm:px-6 py-4 border-b border-gray-100/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="text-xl" aria-hidden="true">🩺</span>
            <div>
                <h2 class="font-semibold text-gray-900">{{ __('borrower.engagement.financial_health.title') }}</h2>
                <p class="text-xs text-gray-500">{{ __('borrower.engagement.financial_health.subtitle') }}</p>
            </div>
        </div>
        @if (! empty($health['next_action']['label']))
            <a href="{{ $health['next_action']['url'] ?? route('site.borrower.profile') }}"
               class="inline-flex items-center justify-center text-xs font-semibold px-4 py-2 rounded-xl bg-brand text-white hover:bg-brand-light">
                {{ $health['next_action']['label'] }} →
            </a>
        @endif
    </div>

    <div class="p-5 sm:p-6 grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.profile.completion_label') }}</p>
            <p class="mt-2 text-2xl font-black text-brand tabular-nums">{{ $health['profile_completion']['percent'] ?? 0 }}%</p>
            <p class="text-xs text-gray-600 mt-1">{{ __('borrower.engagement.profile_strength') }}: <span class="font-semibold">{{ $health['profile_completion']['strength']['label'] ?? '—' }}</span></p>
        </div>

        <div class="rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.membership.grade_label') }}</p>
            <p class="mt-2 text-lg font-bold text-gray-900">{{ $health['grade']['label'] ?? __('borrower.loan_profile.grades.bronze') }}</p>
            <x-site.star-rating
                class="mt-1 text-lg"
                :filled="$health['grade']['filled'] ?? 1"
                :max="$health['grade']['max'] ?? 4"
                :label="($health['grade']['label'] ?? '').' '.($health['grade']['filled'] ?? 1).'/'.($health['grade']['max'] ?? 4)"
            />
        </div>

        <div class="rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.engagement.trust.title') }}</p>
            <x-site.star-rating
                class="mt-2 text-lg"
                :filled="$health['trust_score']['filled'] ?? 0"
                :max="$health['trust_score']['max'] ?? 5"
                :label="(($health['trust_score']['percent'] ?? 0).'%')"
            />
            <p class="text-xs text-gray-600 mt-1">{{ $health['trust_score']['percent'] ?? 0 }}% {{ __('borrower.engagement.trust.score') }}</p>
        </div>

        <div class="rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.engagement.streak.title_short') }}</p>
            <p class="mt-2 text-base font-bold text-gray-900">{{ $health['repayment_score']['label'] ?? '—' }}</p>
            <p class="text-xs text-gray-600 mt-1">{{ __('borrower.dashboard.snapshot.membership') }}: {{ $health['membership']['label'] ?? '—' }}</p>
        </div>

        <div class="rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.engagement.referral_progress') }}</p>
            <p class="mt-2 text-base font-bold text-gray-900">{{ ($health['referral_progress']['current'] ?? 0) }} / {{ ($health['referral_progress']['target'] ?? 5) }}</p>
            <p class="text-xs text-gray-600 mt-1">{{ ($health['loyalty_points'] ?? 0) }} {{ __('borrower.engagement.points_short') }}</p>
        </div>
    </div>
</section>
