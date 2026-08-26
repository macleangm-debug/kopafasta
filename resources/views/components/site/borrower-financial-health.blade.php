@props(['health'])

@php
    $cards = [
        [
            'label' => __('borrower.profile.completion_label'),
            'body' => ($health['profile_completion']['percent'] ?? 0).'%',
            'body_class' => 'mt-2 text-2xl font-black text-brand tabular-nums',
            'hint' => __('borrower.engagement.profile_strength').': '.($health['profile_completion']['strength']['label'] ?? '—'),
            'stars' => null,
        ],
        [
            'label' => __('borrower.membership.grade_label'),
            'body' => $health['grade']['label'] ?? __('borrower.loan_profile.grades.bronze'),
            'body_class' => 'mt-2 text-lg font-bold text-gray-900',
            'hint' => null,
            'stars' => [
                'filled' => $health['grade']['filled'] ?? 1,
                'max' => $health['grade']['max'] ?? 4,
                'label' => ($health['grade']['label'] ?? '').' '.($health['grade']['filled'] ?? 1).'/'.($health['grade']['max'] ?? 4),
            ],
        ],
        [
            'label' => __('borrower.engagement.trust.title'),
            'body' => null,
            'body_class' => '',
            'hint' => ($health['trust_score']['percent'] ?? 0).'% '.__('borrower.engagement.trust.score'),
            'stars' => [
                'filled' => $health['trust_score']['filled'] ?? 0,
                'max' => $health['trust_score']['max'] ?? 5,
                'label' => (($health['trust_score']['percent'] ?? 0).'%'),
            ],
        ],
        [
            'label' => __('borrower.engagement.streak.title_short'),
            'body' => $health['repayment_score']['label'] ?? '—',
            'body_class' => 'mt-2 text-base font-bold text-gray-900',
            'hint' => __('borrower.dashboard.snapshot.membership').': '.($health['membership']['label'] ?? '—'),
            'stars' => null,
        ],
        [
            'label' => __('borrower.engagement.referral_progress'),
            'body' => ($health['referral_progress']['current'] ?? 0).' / '.($health['referral_progress']['target'] ?? 5),
            'body_class' => 'mt-2 text-base font-bold text-gray-900',
            'hint' => ($health['loyalty_points'] ?? 0).' '.__('borrower.engagement.points_short'),
            'stars' => null,
        ],
    ];
@endphp

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

    <div class="p-5 sm:p-6">
        <div class="flex gap-3 overflow-x-auto snap-x snap-mandatory pb-1 -mx-1 px-1 scrollbar-none lg:grid lg:grid-cols-5 lg:overflow-visible lg:pb-0">
            @foreach ($cards as $card)
                <div class="min-w-[78%] sm:min-w-[46%] lg:min-w-0 snap-start rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-4">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $card['label'] }}</p>
                    @if (filled($card['body']))
                        <p class="{{ $card['body_class'] }}">{{ $card['body'] }}</p>
                    @endif
                    @if (! empty($card['stars']))
                        <x-site.star-rating
                            class="mt-2 text-lg"
                            :filled="$card['stars']['filled']"
                            :max="$card['stars']['max']"
                            :label="$card['stars']['label']"
                        />
                    @endif
                    @if (filled($card['hint']))
                        <p class="text-xs text-gray-600 mt-1">{{ $card['hint'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
