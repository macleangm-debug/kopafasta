<x-site.borrower-layout :title="brand_title('Kopafasta Plus')" active="plus" content-width="wide">
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

            @if ($plusActive && $today)
                <div class="relative mt-5 rounded-2xl bg-white/10 ring-1 ring-white/15 p-4 sm:p-5">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('plus.today.title') }}</p>
                    <p class="text-xs text-white/70 mt-1">{{ now()->locale(app()->getLocale())->isoFormat('dddd, D MMMM') }}@if($name) · {{ __('plus.today.hello', ['name' => $name]) }} 👋@endif</p>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-white/55 mt-3">{{ $today['eyebrow'] }}</p>
                    <h2 class="text-lg font-bold mt-1">{{ $today['title'] }}</h2>
                    <p class="text-sm text-white/85 mt-1">{{ $today['body'] }}</p>
                    <a href="{{ $today['cta_url'] }}" class="mt-4 inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand px-5 py-2.5 text-sm font-bold shadow-sm ring-1 ring-brand-gold/40">{{ $today['cta_label'] }} →</a>
                </div>
            @elseif ($plusExpired ?? false)
                <div class="relative mt-5 rounded-2xl bg-white/10 ring-1 ring-white/15 p-4">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('plus.home.expired_kicker') }}</p>
                    <p class="text-sm font-semibold mt-1">{{ __('plus.home.expired_body') }}</p>
                </div>
            @elseif (! $plusActive)
                <div class="relative mt-5 rounded-2xl bg-white/10 ring-1 ring-white/15 p-4">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('plus.home.next_step') }}</p>
                    <p class="text-sm font-semibold mt-1">{{ __('plus.home.access', ['amount' => format_money($access)]) }}</p>
                </div>
            @endif

            @if ($plusNeedsRenewal ?? false)
                <form method="post" action="{{ route('site.borrower.plus.renew') }}" class="relative mt-4">
                    @csrf
                    <button class="inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand px-5 py-2.5 text-sm font-bold shadow-sm ring-1 ring-brand-gold/40">{{ __('plus.home.renew') }}</button>
                    @if ($plusActive && ($plusDaysRemaining ?? null) !== null)
                        <p class="mt-2 text-xs text-white/70">{{ __('plus.home.renew_soon', ['days' => $plusDaysRemaining]) }}</p>
                    @endif
                </form>
            @endif
        </section>

        @if ($plusActive)
            <div class="grid sm:grid-cols-2 gap-3">
                <a href="{{ route('site.borrower.plus.offers') }}" class="rounded-2xl bg-white ring-1 ring-brand/15 p-4 hover:ring-brand/30">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">✦ {{ __('plus.home.exclusive_kicker') }}</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900">{{ __('plus.home.exclusive_offers', ['count' => (int) $offers]) }}</p>
                    <p class="mt-2 text-sm font-bold text-brand">{{ __('plus.home.see_offers') }} →</p>
                </a>
                <a href="{{ route('site.borrower.engagement', ['tab' => 'rewards']) }}" class="rounded-2xl bg-white ring-1 ring-brand/15 p-4 hover:ring-brand/30">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.home.your_rewards') }}</p>
                    <p class="mt-2 text-2xl font-black tabular-nums text-gray-900">{{ number_format((int) ($rewardsDash['balance'] ?? $loyaltyBalance ?? $rewardBalance ?? 0)) }} <span class="text-sm font-semibold text-gray-500">pts</span></p>
                    @if (($rewardsDash['to_next'] ?? 0) > 0)
                        <p class="mt-1 text-sm text-gray-600">{{ __('borrower.rewards.to_next', ['points' => number_format($rewardsDash['to_next'])]) }}</p>
                    @endif
                    <p class="mt-2 text-sm font-bold text-brand">{{ __('plus.home.see_rewards') }} →</p>
                </a>
            </div>
        @endif

        @if ($plusActive || ($plusExpired ?? false))
            <section>
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-3">{{ __('plus.home.rooms_title') }}</p>
                @if ($plusExpired ?? false)
                    <p class="text-sm text-gray-600 mb-3">{{ __('plus.home.locked_rooms') }}</p>
                @endif
                @php
                    $roomLocked = (bool) ($plusExpired ?? false);
                    $roomHref = $roomLocked ? route('site.borrower.plus.home') : null;
                    $roomCta = $roomLocked ? __('plus.home.renew') : __('plus.home.open_room');
                @endphp
                <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory pb-2 items-stretch -mx-1 px-1">
                    <x-site.plus-room-card
                        :href="$roomLocked ? '#' : route('site.borrower.plus.money')"
                        icon="💸"
                        :title="__('plus.home.money')"
                        :stat="format_money_compact((float) ($money['left'] ?? 0))"
                        :stat-class="(float) ($money['left'] ?? 0) > 0 ? 'mt-1.5 text-lg font-bold tabular-nums text-emerald-700' : 'mt-1.5 text-lg font-bold tabular-nums text-gray-900'"
                        :hint="__('plus.home.money_left')"
                        :cta="$roomCta"
                        :locked="$roomLocked"
                    />
                    <x-site.plus-room-card
                        :href="$roomLocked ? '#' : route('site.borrower.plus.business')"
                        icon="🏪"
                        :title="__('plus.home.business')"
                        :stat="format_money_compact((float) ($business['week']['sold'] ?? 0))"
                        :stat-class="(float) ($business['week']['sold'] ?? 0) > 0 ? 'mt-1.5 text-lg font-bold tabular-nums text-emerald-700' : 'mt-1.5 text-lg font-bold tabular-nums text-gray-900'"
                        :hint="__('plus.business.sold').' · '.__('plus.business.diff').' '.format_money_compact((float) ($business['week']['difference'] ?? 0))"
                        :cta="$roomCta"
                        :locked="$roomLocked"
                    />
                    <x-site.plus-room-card
                        :href="$roomLocked ? '#' : route('site.borrower.plus.goals')"
                        icon="🎯"
                        :title="__('plus.home.goals')"
                        :stat="$leadGoal ? $leadGoal->title.' · '.$leadGoal->progressPercent().'%' : '—'"
                        :hint="$leadGoal ? __('plus.goals.remaining', ['amount' => format_money_compact($leadGoal->remaining())]) : __('plus.goals.empty')"
                        :cta="$roomCta"
                        :locked="$roomLocked"
                    />
                    <x-site.plus-room-card
                        :href="$roomLocked ? '#' : route('site.borrower.plus.reports')"
                        icon="📊"
                        :title="__('plus.home.reports')"
                        :stat="now()->locale(app()->getLocale())->translatedFormat('F')"
                        :hint="__('plus.home.reports_hint')"
                        :cta="$roomCta"
                        :locked="$roomLocked"
                    />
                    <x-site.plus-room-card
                        :href="$roomLocked ? '#' : route('site.borrower.plus.offers')"
                        icon="🎁"
                        :title="__('plus.home.offers')"
                        :stat="__('plus.home.offers_hint', ['count' => (int) $offers])"
                        :cta="$roomCta"
                        :locked="$roomLocked"
                    />
                    <x-site.plus-room-card
                        :href="$roomLocked ? '#' : route('site.borrower.plus.rewards')"
                        icon="✦"
                        :title="__('plus.home.rewards')"
                        :stat="__('plus.rewards.points', ['balance' => (int) ($loyaltyBalance ?? 0)])"
                        stat-class="mt-1.5 text-3xl font-black tabular-nums tracking-tight text-gray-900"
                        :hint="__('plus.rewards.borrow_line')"
                        :cta="$roomCta"
                        :locked="$roomLocked"
                    />
                    <x-site.plus-room-card
                        :href="$roomLocked ? '#' : route('site.borrower.plus.learn')"
                        icon="📚"
                        :title="__('plus.home.learn')"
                        :stat="$latestLesson ? __('plus.home.learn_live') : __('plus.learn.tagline')"
                        :hint="$latestLesson ? $lessonTitle.' · '.__('plus.learn.minutes', ['minutes' => $latestLesson->duration_minutes ?? 7]).' · '.(($summary['lesson_watched'] ?? false) ? __('plus.home.learn_seen') : __('plus.home.learn_unseen')) : null"
                        :cta="$roomCta"
                        :locked="$roomLocked"
                    />
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
