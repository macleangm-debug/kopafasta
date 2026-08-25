<x-site.borrower-layout :title="brand_title('Kopafasta Plus')" active="dashboard" content-width="wide">
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
        $compact = app(\App\Services\Plus\PlusWorkspaceService::class);
        $money = $summary['money'] ?? null;
        $business = $summary['business'] ?? null;
        $leadGoal = $summary['goals']['lead'] ?? null;
        $name = trim((string) ($customer->first_name ?? ''));
    @endphp

    <div class="space-y-6">
        <section class="kf-premium-panel rounded-2xl p-5 sm:p-6">
            <div class="relative flex flex-wrap items-start justify-between gap-3">
                <x-site.brand-mark size="sm" variant="light" />
                <x-site.grade-badge :grade="$customer->grade ?? 'bronze'" :plus="$plusActive ?? false" size="lg" />
            </div>
            <p class="relative mt-5 text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">Kopafasta Plus</p>
            <p class="relative mt-3 text-xs font-semibold text-white/70">{{ __('plus.home.trust_title') }}</p>
            <h1 class="relative text-2xl sm:text-3xl font-extrabold tracking-tight mt-1">{{ __('plus.home.trust', ['percent' => $trust['percent'] ?? 0, 'label' => $trust['label'] ?? '']) }}</h1>
            <p class="relative mt-2 text-sm text-white/85">{{ __('plus.home.building') }}</p>
            @if (($customer->grade_status ?? '') === 'under_review' || ($customer->grade_integrity ?? '') === 'review')
                <p class="relative mt-3 text-sm font-medium text-amber-100">{{ __('plus.home.reviewing') }}</p>
            @endif
            <div class="relative mt-4 rounded-2xl bg-white/10 ring-1 ring-white/15 p-4">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('plus.home.next_step') }}</p>
                <p class="text-sm font-semibold mt-1">{{ $plusActive ? __('plus.home.next_step_write') : __('plus.home.access', ['amount' => format_money($access)]) }}</p>
            </div>
        </section>

        @if ($plusActive)
            @if ($today)
                <section class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.today.title') }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ now()->locale(app()->getLocale())->isoFormat('dddd, D MMMM') }}@if($name) · {{ __('plus.today.hello', ['name' => $name]) }} 👋@endif</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mt-4">{{ $today['eyebrow'] }}</p>
                    <h2 class="text-lg font-bold text-gray-900 mt-1">{{ $today['title'] }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $today['body'] }}</p>
                    <a href="{{ $today['cta_url'] }}" class="mt-4 inline-flex rounded-xl bg-brand hover:bg-brand-light text-white px-5 py-2.5 text-sm font-semibold">{{ $today['cta_label'] }} →</a>
                </section>
            @endif

            <section>
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.home.summary') }}</p>
                <div class="grid sm:grid-cols-3 gap-3">
                    <a href="{{ route('site.borrower.plus.money') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-4 hover:ring-brand/30">
                        <p class="text-xs font-semibold text-gray-500">{{ __('plus.home.money') }}</p>
                        <p class="text-lg font-bold tabular-nums text-gray-900 mt-1">{{ $compact->compactAmount((float) (($money['left'] ?? 0))) }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('plus.home.money_left') }}</p>
                    </a>
                    <a href="{{ route('site.borrower.plus.business') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-4 hover:ring-brand/30">
                        <p class="text-xs font-semibold text-gray-500">{{ __('plus.home.business') }}</p>
                        <p class="text-lg font-bold tabular-nums text-gray-900 mt-1">{{ $compact->compactAmount((float) (($business['week']['difference'] ?? 0))) }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('plus.home.business_week') }}</p>
                    </a>
                    <a href="{{ route('site.borrower.plus.goals') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-4 hover:ring-brand/30">
                        <p class="text-xs font-semibold text-gray-500">{{ __('plus.home.goals') }}</p>
                        @if ($leadGoal)
                            <p class="text-lg font-bold text-gray-900 mt-1">{{ $leadGoal->title }} · {{ $leadGoal->progressPercent() }}%</p>
                        @else
                            <p class="text-lg font-bold text-gray-900 mt-1">—</p>
                        @endif
                        <p class="text-xs text-brand font-semibold mt-0.5">{{ __('plus.home.continue') }} →</p>
                    </a>
                </div>
            </section>

            <section>
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.home.rooms') }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <a href="{{ route('site.borrower.plus.money') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 hover:ring-brand/30">
                        <p class="font-semibold text-gray-900">{{ __('plus.home.money') }}</p>
                        <p class="text-xl font-bold tabular-nums mt-2">{{ $compact->compactAmount((float) ($money['left'] ?? 0)) }}</p>
                        <p class="text-sm text-gray-500">{{ __('plus.home.money_left') }}</p>
                        <p class="text-sm font-semibold text-brand mt-3">{{ __('plus.home.open_room') }} →</p>
                    </a>
                    <a href="{{ route('site.borrower.plus.business') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 hover:ring-brand/30">
                        <p class="font-semibold text-gray-900">{{ __('plus.home.business') }}</p>
                        <p class="text-sm text-gray-700 mt-2">{{ __('plus.business.sold') }} <span class="font-bold">{{ $compact->compactAmount((float) ($business['week']['sold'] ?? 0)) }}</span></p>
                        <p class="text-sm text-gray-700">{{ __('plus.business.diff') }} <span class="font-bold">{{ $compact->compactAmount((float) ($business['week']['difference'] ?? 0)) }}</span></p>
                        <p class="text-sm font-semibold text-brand mt-3">{{ __('plus.home.open_room') }} →</p>
                    </a>
                    <a href="{{ route('site.borrower.plus.goals') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 hover:ring-brand/30">
                        <p class="font-semibold text-gray-900">{{ __('plus.home.goals') }}</p>
                        <p class="text-sm text-gray-700 mt-2">{{ $leadGoal ? $leadGoal->title.' · '.$leadGoal->progressPercent().'%' : __('plus.goals.empty') }}</p>
                        <p class="text-sm font-semibold text-brand mt-3">{{ __('plus.home.continue') }} →</p>
                    </a>
                    <a href="{{ route('site.borrower.plus.reports') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 hover:ring-brand/30">
                        <p class="font-semibold text-gray-900">{{ __('plus.home.reports') }}</p>
                        <p class="text-sm text-gray-600 mt-2">{{ now()->locale(app()->getLocale())->translatedFormat('F') }}</p>
                        <p class="text-sm font-semibold text-brand mt-3">{{ __('plus.home.open_room') }} →</p>
                    </a>
                    <a href="{{ route('site.borrower.plus.offers') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 hover:ring-brand/30">
                        <p class="font-semibold text-gray-900">{{ __('plus.home.offers') }}</p>
                        <p class="text-sm text-gray-600 mt-2">{{ __('plus.home.offers_hint', ['count' => (int) $offers]) }}</p>
                        <p class="text-sm font-semibold text-brand mt-3">{{ __('plus.home.open_room') }} →</p>
                    </a>
                    <a href="{{ route('site.borrower.plus.rewards') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 hover:ring-brand/30">
                        <p class="font-semibold text-gray-900">{{ __('plus.home.rewards') }}</p>
                        <p class="text-xl font-bold mt-2">{{ __('plus.rewards.points', ['balance' => $rewardBalance]) }}</p>
                        <p class="text-sm text-gray-500">{{ __('plus.rewards.borrow_line') }}</p>
                        <p class="text-sm font-semibold text-brand mt-3">{{ __('plus.home.open_room') }} →</p>
                    </a>
                    <a href="{{ route('site.borrower.plus.learn') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 hover:ring-brand/30 sm:col-span-2">
                        <p class="font-semibold text-gray-900">{{ __('plus.home.learn') }}</p>
                        @if ($latestLesson)
                            <p class="text-sm font-semibold text-gray-800 mt-2">{{ __('plus.home.learn_live') }}</p>
                            <p class="text-sm text-gray-600">{{ $lessonTitle }} · {{ __('plus.learn.minutes', ['minutes' => $latestLesson->duration_minutes ?? 7]) }} · {{ ($summary['lesson_watched'] ?? false) ? __('plus.home.learn_seen') : __('plus.home.learn_unseen') }}</p>
                        @else
                            <p class="text-sm text-gray-600 mt-2">{{ __('plus.learn.tagline') }}</p>
                        @endif
                        <p class="text-sm font-semibold text-brand mt-3">{{ __('plus.today.watch') }} →</p>
                    </a>
                </div>
            </section>

            @if (! empty($summary['upcoming']))
                <section class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold">{{ __('plus.home.up_next') }}</p>
                    <div class="mt-3 space-y-2">
                        @foreach ($summary['upcoming'] as $item)
                            <a href="{{ $item['url'] ?? '#' }}" class="flex justify-between gap-3 text-sm">
                                <span class="font-medium text-gray-800">{{ $item['title'] }}</span>
                                <span class="text-gray-600 tabular-nums">{{ format_money($item['amount']) }} · {{ $item['date']->locale(app()->getLocale())->isoFormat('D MMM') }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <form method="post" action="{{ route('site.borrower.plus.renew') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                @csrf
                <p class="text-sm text-gray-600">{{ __('plus.home.renew_hint') }}</p>
                <p class="text-sm font-semibold text-gray-900 mt-2">{{ $priceText }}</p>
                <button class="mt-3 inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('plus.home.renew') }}</button>
            </form>
        @else
            <section class="kf-premium-panel rounded-2xl p-5 sm:p-6">
                <p class="relative text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">Kopafasta Plus ✦</p>
                <h2 class="relative mt-2 text-2xl sm:text-3xl font-extrabold tracking-tight">{{ __('plus.home.explore') }}</h2>
                <p class="relative mt-2 text-sm text-white/80 max-w-xl">{{ __('plus.home.explore_body') }}</p>
                @if ($latestLesson)
                    <div class="relative mt-5 rounded-2xl bg-white/10 ring-1 ring-white/20 p-4 sm:p-5">
                        <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('plus.learn.this_month') }}</p>
                        <p class="font-semibold text-white mt-1.5 text-lg">{{ $lessonTitle }}</p>
                        <p class="text-sm text-white/75 mt-1">{{ __('plus.learn.preview') }}</p>
                    </div>
                @endif
                <p class="relative mt-6 text-3xl sm:text-4xl font-black tabular-nums tracking-tight">{{ $priceText }}</p>
                <p class="relative mt-1 text-xs text-white/65">{{ __('plus.home.optional') }}</p>
                <form method="post" action="{{ route('site.borrower.plus.join') }}" class="relative mt-5">
                    @csrf
                    <button class="inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand px-6 py-3 font-bold shadow-sm ring-1 ring-brand-gold/40">{{ __('plus.home.join') }}</button>
                </form>
            </section>
        @endif
    </div>
</x-site.borrower-layout>
