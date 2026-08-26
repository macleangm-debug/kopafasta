@props(['customer'])

@php
    $grade = strtolower((string) ($customer->grade ?: 'bronze'));
    $gradeFilled = match ($grade) {
        'platinum' => 4,
        'gold' => 3,
        'silver' => 2,
        default => 1,
    };
    $trust = app(\App\Services\MemberEngagementService::class)->trustScore($customer);
@endphp

<section {{ $attributes->merge(['class' => 'mb-6 rounded-2xl bg-white ring-1 ring-brand/15 p-5 sm:p-6 shadow-sm']) }}>
    <p class="text-[10px] uppercase tracking-[0.18em] font-semibold text-brand">{{ __('borrower.loan_profile.standing_title') }}</p>
    <p class="text-sm text-gray-600 mt-1 max-w-xl">{{ __('borrower.loan_profile.standing_hint') }}</p>

    <div class="mt-4 grid sm:grid-cols-2 gap-3">
        <div class="rounded-xl bg-[#faf8f5] ring-1 ring-gray-200/80 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.standing_grade') }}</p>
            <p class="mt-1 text-lg font-bold text-gray-900">{{ __('borrower.loan_profile.grades.'.$grade) }}</p>
            <x-site.star-rating class="mt-1 text-xl" :filled="$gradeFilled" :max="4" :label="__('borrower.loan_profile.standing_grade').': '.__('borrower.loan_profile.grades.'.$grade)" />
        </div>
        <div class="rounded-xl bg-[#faf8f5] ring-1 ring-gray-200/80 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.standing_trust') }}</p>
            <p class="mt-1 text-lg font-bold text-gray-900 tabular-nums">{{ (int) ($trust['percent'] ?? 0) }}%</p>
            <x-site.star-rating
                class="mt-1 text-xl"
                :filled="$trust['filled'] ?? 0"
                :max="$trust['max'] ?? 5"
                :label="__('borrower.loan_profile.standing_trust').': '.((int) ($trust['percent'] ?? 0)).'%'"
            />
        </div>
    </div>
</section>
