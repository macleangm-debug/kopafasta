<x-site.borrower-layout :title="brand_title('Kopafasta Plus')" active="dashboard">
    <div class="space-y-6">
        <a href="{{ route('site.borrower.plus.home') }}" class="block rounded-2xl bg-white ring-1 ring-brand/10 p-6">
            <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ strtoupper($customer->grade ?? 'bronze') }} ✦</p>
            <h1 class="text-2xl font-semibold text-gray-900 mt-1">{{ __('plus.home.trust', ['percent' => $trust['percent'] ?? 0, 'label' => $trust['label'] ?? '']) }}</h1>
            <p class="text-sm text-gray-600 mt-2">{{ __('plus.home.access', ['amount' => format_money($access)]) }}</p>
            @if (($customer->grade_status ?? '') === 'under_review' || ($customer->grade_integrity ?? '') === 'review')
                <p class="mt-3 text-sm font-medium text-amber-800">{{ __('plus.home.reviewing') }}</p>
            @endif
            <ul class="mt-4 space-y-1 text-sm text-gray-800">
                @foreach ($benefitList as $item)
                    <li>✓ {{ $item }}</li>
                @endforeach
            </ul>
            <p class="mt-4 text-sm font-semibold text-gray-900">{{ $nextGrade['title'] ?? '' }}</p>
            <p class="text-sm text-gray-600">{{ $nextGrade['body'] ?? '' }}</p>
        </a>

        @if ($plusActive)
            <div class="grid sm:grid-cols-2 gap-3">
                <a href="{{ route('site.borrower.plus.money') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                    <p class="font-semibold">{{ __('plus.home.money') }}</p>
                    <p class="text-sm text-gray-600">{{ __('plus.home.money_hint') }}</p>
                </a>
                <a href="{{ route('site.borrower.plus.business') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                    <p class="font-semibold">{{ __('plus.home.business') }}</p>
                    <p class="text-sm text-gray-600">{{ __('plus.home.business_hint') }}</p>
                </a>
                <a href="{{ route('site.borrower.plus.goals') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                    <p class="font-semibold">{{ __('plus.home.goals') }}</p>
                    <p class="text-sm text-gray-600">{{ __('plus.home.goals_hint') }}</p>
                </a>
                <a href="{{ route('site.borrower.plus.reports') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                    <p class="font-semibold">{{ __('plus.home.reports') }}</p>
                    <p class="text-sm text-gray-600">{{ __('plus.home.reports_hint') }}</p>
                </a>
                <a href="{{ route('site.borrower.plus.offers') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                    <p class="font-semibold">{{ __('plus.home.offers') }}</p>
                    <p class="text-sm text-gray-600">{{ __('plus.home.offers_hint', ['count' => $offers->count()]) }}</p>
                </a>
                <a href="{{ route('site.borrower.plus.rewards') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                    <p class="font-semibold">{{ __('plus.home.rewards') }}</p>
                    <p class="text-sm text-gray-600">{{ __('plus.home.rewards_hint', ['balance' => $rewardBalance]) }}</p>
                </a>
                <a href="{{ route('site.borrower.plus.learn') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                    <p class="font-semibold">{{ __('plus.home.learn') }}</p>
                    <p class="text-sm text-gray-600">{{ __('plus.home.learn_hint') }}</p>
                </a>
            </div>
            <form method="post" action="{{ route('site.borrower.plus.renew') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                @csrf
                <p class="text-sm text-gray-600">{{ __('plus.home.renew_hint') }}</p>
                <button class="mt-3 inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('plus.home.renew') }}</button>
            </form>
        @else
            <div class="rounded-2xl bg-brand/5 ring-1 ring-brand/15 p-6 space-y-4">
                <p class="font-semibold text-gray-900">{{ __('plus.home.explore') }}</p>
                <p class="text-sm text-gray-600">{{ __('plus.home.explore_body') }}</p>
                <ul class="space-y-1 text-sm text-gray-800">
                    @foreach ($benefitList as $item)
                        <li>✓ {{ $item }}</li>
                    @endforeach
                </ul>
                <p class="text-sm font-medium text-gray-900">{{ __('plus.home.price', ['amount' => format_money($price['amount'] ?? 0)]) }}</p>
                <p class="text-xs text-gray-500">{{ __('plus.home.optional') }}</p>
                <form method="post" action="{{ route('site.borrower.plus.join') }}">
                    @csrf
                    <button class="inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('plus.home.join') }}</button>
                </form>
            </div>
        @endif
    </div>
</x-site.borrower-layout>
