<x-site.borrower-layout :title="brand_title('Kopafasta Plus')" active="dashboard">
    @php
        $days = (int) ($periodDays ?? 365);
        $priceAmount = format_money($price['amount'] ?? 0);
        $priceText = $days >= 360
            ? __('plus.home.price_year', ['amount' => $priceAmount])
            : ($days === 30
                ? __('plus.home.price', ['amount' => $priceAmount])
                : __('plus.home.price_days', ['amount' => $priceAmount, 'days' => $days]));
        $lessonTitle = $latestLesson
            ? (app()->getLocale() === 'sw' ? ($latestLesson->title_sw ?: $latestLesson->title_en) : $latestLesson->title_en)
            : null;
    @endphp

    <div class="space-y-6">
        <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#0B3D32] via-[#127A5F] to-[#082f27] text-white shadow-[0_18px_40px_rgba(8,47,39,0.35)] ring-1 ring-brand-gold/30 p-5 sm:p-6">
            <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-3">
                <x-site.brand-mark size="sm" variant="light" />
                <x-site.grade-badge :grade="$customer->grade ?? 'bronze'" :plus="$plusActive ?? false" />
            </div>
            <p class="relative mt-5 text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">Kopafasta Plus</p>
            <h1 class="relative text-2xl sm:text-3xl font-extrabold tracking-tight mt-1">{{ __('plus.home.trust', ['percent' => $trust['percent'] ?? 0, 'label' => $trust['label'] ?? '']) }}</h1>
            <p class="relative text-lg font-semibold mt-3">{{ __('plus.home.access', ['amount' => format_money($access)]) }}</p>
            @if (($customer->grade_status ?? '') === 'under_review' || ($customer->grade_integrity ?? '') === 'review')
                <p class="relative mt-3 text-sm font-medium text-amber-100">{{ __('plus.home.reviewing') }}</p>
            @endif
            <ul class="relative mt-4 space-y-1.5 text-sm text-white/90">
                @foreach ($benefitList as $item)
                    <li class="flex gap-2"><span class="text-brand-gold">✓</span> <span>{{ $item }}</span></li>
                @endforeach
            </ul>
            <p class="relative mt-5 text-sm font-semibold text-brand-gold">{{ $nextGrade['title'] ?? '' }}</p>
            <p class="relative text-sm text-white/80">{{ $nextGrade['body'] ?? '' }}</p>
        </section>

        @if ($plusActive)
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach ([
                    ['money', 'plus.home.money', 'plus.home.money_hint'],
                    ['business', 'plus.home.business', 'plus.home.business_hint'],
                    ['goals', 'plus.home.goals', 'plus.home.goals_hint'],
                    ['reports', 'plus.home.reports', 'plus.home.reports_hint'],
                    ['offers', 'plus.home.offers', 'plus.home.offers_hint', ['count' => $offers->count()]],
                    ['rewards', 'plus.home.rewards', 'plus.home.rewards_hint', ['balance' => $rewardBalance]],
                    ['learn', 'plus.home.learn', 'plus.home.learn_hint'],
                ] as $room)
                    <a href="{{ route('site.borrower.plus.'.$room[0]) }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 hover:ring-brand/30 transition">
                        <p class="font-semibold text-gray-900">{{ __($room[1]) }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ __($room[2], $room[3] ?? []) }}</p>
                    </a>
                @endforeach
            </div>
            @if ($latestLesson)
                <a href="{{ route('site.borrower.plus.lesson', $latestLesson) }}" class="block rounded-2xl bg-white ring-1 ring-brand/15 p-5">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.learn.this_month') }}</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ $lessonTitle }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ __('plus.learn.minutes', ['minutes' => $latestLesson->duration_minutes ?? 7]) }}</p>
                </a>
            @endif
            <form method="post" action="{{ route('site.borrower.plus.renew') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                @csrf
                <p class="text-sm text-gray-600">{{ __('plus.home.renew_hint') }}</p>
                <p class="text-sm font-semibold text-gray-900 mt-2">{{ $priceText }}</p>
                <button class="mt-3 inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('plus.home.renew') }}</button>
            </form>
        @else
            <div class="rounded-2xl bg-white ring-1 ring-brand/15 p-6 space-y-4">
                <p class="font-semibold text-gray-900 text-lg">{{ __('plus.home.explore') }}</p>
                <p class="text-sm text-gray-600">{{ __('plus.home.explore_body') }}</p>
                @if ($latestLesson)
                    <div class="rounded-xl bg-brand/5 ring-1 ring-brand/10 p-4">
                        <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.learn.this_month') }}</p>
                        <p class="font-semibold text-gray-900 mt-1">{{ $lessonTitle }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ __('plus.learn.preview') }}</p>
                    </div>
                @endif
                <p class="text-sm font-semibold text-gray-900">{{ $priceText }}</p>
                <p class="text-xs text-gray-500">{{ __('plus.home.optional') }}</p>
                <form method="post" action="{{ route('site.borrower.plus.join') }}">
                    @csrf
                    <button class="inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('plus.home.join') }}</button>
                </form>
            </div>
        @endif
    </div>
</x-site.borrower-layout>
