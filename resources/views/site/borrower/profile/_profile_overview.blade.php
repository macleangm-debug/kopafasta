@props(['customer'])

@php
    $builder = app(\App\Services\ProfileSectionBuilderService::class);
    $sectionsByKey = collect($builder->hubCards($customer))->keyBy('key');
    $summary = app(\App\Services\ProfileCompletionService::class)->completionSummary($customer);
    $percent = (int) ($summary['percent'] ?? 0);
    $remainingCount = (int) ($summary['remaining_count'] ?? count($summary['actionable'] ?? []));
    $continueItem = collect($summary['actionable'] ?? [])->first(fn ($item) => ! empty($item['url']));
    $continueUrl = $continueItem['url'] ?? route('site.borrower.profile', ['section' => 'personal']);

    // Five layperson categories only — no workflow status badges on the landing.
    $order = ['personal', 'activity', 'residence', 'payment', 'assets'];
@endphp

<section class="mb-6 rounded-2xl ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/40 via-white to-white p-5 sm:p-6">
    <p class="text-[10px] uppercase tracking-widest font-bold text-brand">{{ __('borrower.profile.hub.sections_title') }}</p>
    <p class="mt-2 text-2xl font-extrabold text-gray-900 tracking-tight"
       data-kf-completion-percent
       data-percent-template="{{ __('borrower.profile.completion_summary_percent', ['percent' => ':percent']) }}">
        {{ __('borrower.profile.completion_summary_percent', ['percent' => $percent]) }}
    </p>
    <div class="mt-3 h-2.5 bg-white/80 ring-1 ring-brand/10 rounded-full overflow-hidden" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full bg-brand transition-all" data-kf-completion-bar style="width: {{ min(100, max(0, $percent)) }}%"></div>
    </div>

    @if ($remainingCount > 0)
        <p class="mt-4 text-sm font-semibold text-gray-800">
            {{ trans_choice('borrower.profile.hub.things_remaining', $remainingCount, ['count' => $remainingCount]) }}
        </p>
        <a href="{{ $continueUrl }}"
           class="mt-3 inline-flex items-center gap-1.5 text-sm font-bold text-brand hover:underline">
            {{ __('borrower.profile.hub.continue_completing') }}
            <span aria-hidden="true">→</span>
        </a>
    @else
        <p class="mt-4 text-sm font-semibold text-emerald-800">{{ __('borrower.profile.hero_completion_done') }}</p>
        <p class="mt-1 text-sm text-gray-600">{{ __('borrower.profile.hub.all_set_hint') }}</p>
    @endif
</section>

<section class="mb-6">
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($order as $key)
            @php
                $section = $sectionsByKey->get($key);
                if (! $section) {
                    continue;
                }
                $isComplete = ($section['status'] ?? '') === 'complete';
                $isAssets = $key === 'assets';
                $isPayment = $key === 'payment';
                $progress = $section['progress'] ?? null;
                $remaining = (int) ($progress['remaining'] ?? count($section['missing'] ?? []));

                if ($isAssets) {
                    $progressLabel = __('borrower.profile.status.optional');
                    $cta = __('borrower.profile.hub.add_optional');
                    $showTick = false;
                } elseif ($isComplete) {
                    $progressLabel = ! empty($progress['total'])
                        ? __('borrower.profile.hub.of_complete', [
                            'done' => $progress['total'],
                            'total' => $progress['total'],
                        ])
                        : __('borrower.profile.status.complete');
                    $cta = __('borrower.profile.status.complete');
                    $showTick = true;
                } elseif ($progress && (int) ($progress['total'] ?? 0) > 0) {
                    $progressLabel = __('borrower.profile.hub.of_complete', [
                        'done' => $progress['done'],
                        'total' => $progress['total'],
                    ]);
                    $cta = $isPayment && (int) ($progress['done'] ?? 0) === 0
                        ? __('borrower.profile.hub.add_account')
                        : __('borrower.profile.hub.continue');
                    $showTick = false;
                } else {
                    $progressLabel = __('borrower.profile.hub.of_complete', ['done' => 0, 'total' => 1]);
                    $cta = __('borrower.profile.hub.continue');
                    $showTick = false;
                }
            @endphp
            <a href="{{ $section['url'] }}"
               data-kf-share="kf-prof-{{ $key }}"
               class="group rounded-2xl ring-1 ring-gray-200/80 hover:ring-brand/30 bg-white p-5 transition hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <span class="text-2xl leading-none" aria-hidden="true">{{ $section['icon'] ?? '📋' }}</span>
                    @if ($showTick)
                        <span class="size-7 rounded-full grid place-items-center bg-gradient-to-br from-brand to-brand-light text-brand-gold shadow-sm ring-2 ring-brand-gold/40"
                              title="{{ __('borrower.profile.section_complete') }}"
                              aria-label="{{ __('borrower.profile.section_complete') }}">
                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                    @endif
                </div>
                <h3 class="mt-4 font-bold text-gray-900 group-hover:text-brand transition">{{ $section['label'] }}</h3>
                <p class="text-xs text-gray-500 mt-1">{{ $progressLabel }}</p>
                @if (! $isAssets && ! $isComplete && $remaining > 0)
                    <p class="mt-1 text-xs font-semibold text-amber-800">
                        {{ trans_choice('borrower.profile.hub.remaining_count', $remaining, ['count' => $remaining]) }}
                    </p>
                @endif
                @if ($isAssets)
                    <p class="mt-3 text-xs text-gray-500">
                        @if (empty($section['count']))
                            {{ __('borrower.profile.hub.optional_none_added') }}
                        @else
                            {{ __('borrower.profile.hub.optional_for_apply') }}
                        @endif
                    </p>
                @endif
                <p class="mt-4 text-xs font-semibold {{ $showTick ? 'text-emerald-700' : 'text-brand' }}">
                    @if ($showTick)
                        ✓ {{ $cta }}
                    @else
                        {{ $cta }} →
                    @endif
                </p>
            </a>
        @endforeach
    </div>
</section>

<section class="space-y-5">
    @php
        $menuGroups = [
            [
                'title' => __('borrower.profile.hub.group_security'),
                'items' => [
                    ['href' => route('site.borrower.profile', ['section' => 'security']), 'label' => __('borrower.profile.security'), 'icon' => '🔒'],
                    ['href' => route('site.borrower.settings'), 'label' => __('borrower.nav.settings'), 'icon' => '⚙️'],
                ],
            ],
            [
                'title' => __('borrower.profile.hub.group_rewards'),
                'items' => [
                    ['href' => route('site.borrower.engagement', ['tab' => 'rewards']), 'label' => __('borrower.nav.rewards'), 'icon' => '🎁'],
                    ['href' => route('site.borrower.engagement', ['tab' => 'referrals']), 'label' => __('borrower.nav.referrals'), 'icon' => '🤝'],
                ],
            ],
            [
                'title' => __('borrower.profile.hub.group_help'),
                'items' => [
                    ['href' => route('site.borrower.support'), 'label' => __('borrower.layout.help_center'), 'icon' => '💬'],
                ],
            ],
        ];
    @endphp
    @foreach ($menuGroups as $group)
        <div>
            <p class="text-[10px] uppercase tracking-widest font-bold text-gray-500 mb-2">{{ $group['title'] }}</p>
            {{-- Mobile: peek-carousel within the group. Desktop: compact responsive row. --}}
            <div class="flex gap-3 overflow-x-auto pb-1 snap-x snap-mandatory sm:overflow-visible sm:grid sm:grid-cols-2 lg:grid-cols-3 sm:pb-0"
                 style="-webkit-overflow-scrolling: touch;">
                @foreach ($group['items'] as $item)
                    <a href="{{ $item['href'] }}"
                       class="snap-start shrink-0 w-[78%] sm:w-auto rounded-2xl ring-1 ring-gray-200/80 bg-white px-4 py-3.5 hover:ring-brand/30 hover:shadow-sm transition flex items-center gap-3">
                        <span class="text-xl leading-none" aria-hidden="true">{{ $item['icon'] }}</span>
                        <span class="min-w-0 flex-1 font-semibold text-sm text-gray-900 truncate">{{ $item['label'] }}</span>
                        <span class="text-brand shrink-0" aria-hidden="true">→</span>
                    </a>
                @endforeach
                @if (count($group['items']) === 1)
                    {{-- Peek spacer so a single mobile card still hints at group structure --}}
                    <div class="snap-start shrink-0 w-[18%] sm:hidden" aria-hidden="true"></div>
                @endif
            </div>
        </div>
    @endforeach
    <form method="POST" action="{{ route('site.logout') }}" class="pt-4 mt-2 border-t border-gray-200">
        @csrf
        <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-white ring-1 ring-rose-200 text-rose-700 hover:bg-rose-50 text-sm font-bold py-3.5">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H9"/>
            </svg>
            {{ __('borrower.layout.sign_out') }}
        </button>
    </form>
</section>
