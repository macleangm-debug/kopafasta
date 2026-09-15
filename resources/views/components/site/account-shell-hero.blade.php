@props([
    'mode' => 'identity', // identity | contextual
    'title' => null,
    'body' => null, // short description under contextual title
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
    'completionCtaUrl' => null,
    'completionCtaLabel' => null,
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
                @if (filled($body))
                    <p class="text-sm sm:text-base text-white/80 leading-relaxed max-w-xl">{{ $body }}</p>
                @endif
            @else
                <div class="flex items-start gap-3 sm:gap-4 min-w-0">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="" class="size-14 sm:size-16 rounded-2xl object-cover ring-2 ring-brand-gold/50 bg-white/10 shrink-0">
                    @else
                        <div class="size-14 sm:size-16 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 text-white grid place-items-center text-xl font-bold shrink-0">
                            {{ $initial }}
                        </div>
                    @endif
                    <div class="min-w-0 pt-0.5 flex-1">
                        <p class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight text-white break-words">{{ $displayName }}</p>
                        @if (filled($memberNo))
                            <p class="text-sm font-mono mt-1.5 text-white/75 break-all">{{ $memberNo }}</p>
                        @endif
                    </div>
                </div>
                @if ($completionPercent !== null)
                    @php $pct = max(0, min(100, (int) $completionPercent)); @endphp
                    <div class="w-full max-w-md space-y-3"
                         data-kf-completion-hero
                         data-kf-completion-done="{{ $pct >= 100 ? '1' : '0' }}">
                        <p class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm sm:text-base font-extrabold shadow-sm ring-1 transition
                                   {{ $pct >= 100
                                        ? 'bg-brand-gold text-brand ring-brand-gold/50'
                                        : 'bg-white/15 text-brand-gold ring-white/25' }}"
                           data-kf-completion-percent
                           data-percent-template="{{ __('borrower.profile.hero_completion_percent', ['percent' => ':percent']) }}"
                           data-kf-completion-done-label="{{ __('borrower.profile.hero_completion_done') }}">
                            @if ($pct >= 100)
                                {{ __('borrower.profile.hero_completion_done') }}
                            @else
                                {{ __('borrower.profile.hero_completion_percent', ['percent' => $pct]) }}
                            @endif
                        </p>
                        <div class="h-3.5 rounded-full bg-white/20 overflow-hidden ring-1 ring-white/10"
                             role="progressbar"
                             aria-valuenow="{{ $pct }}"
                             aria-valuemin="0"
                             aria-valuemax="100"
                             data-kf-completion-track>
                            <div class="h-full rounded-full bg-brand-gold transition-[width] duration-300"
                                 data-kf-completion-bar
                                 style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endif
            @endif

            @php
                $showCompletionCta = $completionPercent !== null
                    && (int) $completionPercent < 100
                    && filled($completionCtaUrl)
                    && filled($completionCtaLabel);
                $showPrimaryCta = filled($ctaUrl) && filled($ctaLabel);
            @endphp
            @if ($showCompletionCta || $showPrimaryCta)
                <div class="flex flex-wrap gap-2" data-kf-hero-cta-row>
                    @if (filled($completionCtaUrl) && filled($completionCtaLabel))
                        <a href="{{ $completionCtaUrl }}"
                           data-loading="click"
                           data-kf-motion="pop"
                           data-kf-completion-cta
                           @class([
                               'inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white text-brand hover:bg-white/90 shadow-sm',
                               'hidden' => ! $showCompletionCta,
                           ])>
                            {{ $completionCtaLabel }} →
                        </a>
                    @endif
                    @if ($showPrimaryCta)
                        <a href="{{ $ctaUrl }}"
                           data-loading="click"
                           data-kf-motion="pop"
                           class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white text-brand hover:bg-white/90 shadow-sm">
                            {{ $ctaLabel }} →
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
