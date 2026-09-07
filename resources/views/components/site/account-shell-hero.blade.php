@props([
    'mode' => 'identity', // identity | contextual
    'title' => null,
    'displayName' => null,
    'memberNo' => null,
    'photoUrl' => null,
    'initial' => '?',
    'grade' => null,
    'plus' => false,
    'showGradeBadge' => true,
    'badgeLabel' => null, // optional partner/status label instead of grade
    'ctaUrl' => null,
    'ctaLabel' => null,
    'completionPercent' => null, // identity hero only; canonical ProfileCompletionService percent
])

{{-- Shared account-shell hero (borrower + partner). Dashboard Hero language. --}}
<section class="mb-6 rounded-2xl p-5 sm:p-6 relative overflow-hidden kf-premium-panel">
    <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
    <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_rgba(245,200,66,0.45),_transparent_55%)] pointer-events-none" aria-hidden="true"></div>

    <div class="relative">
        <div class="flex items-start justify-between gap-3">
            <x-site.brand-mark size="sm" variant="light" />
            @if ($mode === 'identity' && $showGradeBadge && filled($grade))
                <x-site.grade-badge :grade="$grade" :plus="(bool) $plus" size="lg" class="shrink-0" />
            @elseif ($mode === 'identity' && filled($badgeLabel))
                <span class="inline-flex items-center rounded-full bg-white/15 text-white px-3 py-1 text-[10px] font-bold uppercase tracking-[0.14em] ring-1 ring-white/25 shrink-0">
                    {{ $badgeLabel }}
                </span>
            @endif
        </div>

        <div class="mt-5 space-y-4 max-w-2xl">
            @if ($mode === 'contextual')
                <p class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight text-white">
                    {{ $title ?: __('borrower.membership.my_card') }}
                </p>
            @else
                <div class="flex items-start gap-3 sm:gap-4 min-w-0">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="" class="size-14 sm:size-16 rounded-2xl object-cover ring-2 ring-brand-gold/50 bg-white/10 shrink-0">
                    @else
                        <div class="size-14 sm:size-16 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 text-white grid place-items-center text-xl font-bold shrink-0">
                            {{ $initial }}
                        </div>
                    @endif
                    <div class="min-w-0 pt-0.5">
                        <p class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight text-white break-words">{{ $displayName }}</p>
                        @if (filled($memberNo))
                            <p class="text-sm font-mono mt-1.5 text-white/75 break-all">{{ $memberNo }}</p>
                        @endif
                        @if ($completionPercent !== null)
                            @php $pct = max(0, min(100, (int) $completionPercent)); @endphp
                            @if ($pct >= 100)
                                <p class="mt-2 text-xs font-semibold text-brand-gold">{{ __('borrower.profile.hero_completion_done') }}</p>
                            @else
                                <div class="mt-2 max-w-[11rem]">
                                    <p class="text-xs font-semibold text-white/85">{{ __('borrower.profile.hero_completion_percent', ['percent' => $pct]) }}</p>
                                    <div class="mt-1.5 h-1.5 rounded-full bg-white/15 overflow-hidden" aria-hidden="true">
                                        <div class="h-full rounded-full bg-brand-gold" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

            @if ($ctaUrl && $ctaLabel)
                <div class="flex flex-wrap gap-2">
                    <a href="{{ $ctaUrl }}"
                       data-loading="click"
                       data-kf-motion="pop"
                       class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white text-brand hover:bg-white/90 shadow-sm">
                        {{ $ctaLabel }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</section>
