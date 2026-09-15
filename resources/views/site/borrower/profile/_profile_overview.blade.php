@props(['customer'])

@php
    $builder = app(\App\Services\ProfileSectionBuilderService::class);
    $sectionsByKey = collect($builder->hubCards($customer))->keyBy('key');
    $summary = app(\App\Services\ProfileCompletionService::class)->completionSummary($customer);
    $percent = (int) ($summary['percent'] ?? 0);
    $remainingCount = (int) ($summary['remaining_count'] ?? count($summary['actionable'] ?? []));
    $continueItem = collect($summary['actionable'] ?? [])->first(fn ($item) => ! empty($item['url']));
    $continueUrl = $continueItem['url'] ?? route('site.borrower.profile', ['section' => 'personal']);

    // Layperson order — same five categories, no workflow badges / gap lists on the landing page.
    $order = ['personal', 'activity', 'residence', 'payment', 'assets'];
@endphp

<section class="mb-6 rounded-2xl ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/40 via-white to-white p-5 sm:p-6">
    <p class="text-[10px] uppercase tracking-widest font-bold text-brand">{{ __('borrower.profile.hub.sections_title') }}</p>
    <p class="mt-2 text-2xl font-extrabold text-gray-900 tracking-tight">
        {{ __('borrower.profile.completion_summary_percent', ['percent' => $percent]) }}
    </p>
    <div class="mt-3 h-2.5 bg-white/80 ring-1 ring-brand/10 rounded-full overflow-hidden" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full bg-brand transition-all" style="width: {{ min(100, max(0, $percent)) }}%"></div>
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

<section class="mb-6 space-y-3">
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
            if ($isPayment && empty($progress)) {
                $done = (int) ($section['count'] ?? 0) > 0 ? 1 : 0;
                $progress = ['done' => $done, 'total' => 1];
            }

            if ($isAssets) {
                $progressLabel = __('borrower.profile.status.optional');
                $cta = __('borrower.profile.hub.add_optional');
            } elseif ($isComplete) {
                $progressLabel = __('borrower.profile.status.complete');
                $cta = __('borrower.profile.hub.view_edit');
            } elseif ($progress) {
                $progressLabel = __('borrower.profile.hub.of_complete', [
                    'done' => $progress['done'],
                    'total' => $progress['total'],
                ]);
                $cta = $isPayment && (int) ($progress['done'] ?? 0) === 0
                    ? __('borrower.profile.hub.add_account')
                    : __('borrower.profile.hub.continue');
            } else {
                $progressLabel = __('borrower.profile.hub.of_complete', ['done' => 0, 'total' => 1]);
                $cta = __('borrower.profile.hub.continue');
            }
        @endphp
        <a href="{{ $section['url'] }}"
           data-kf-share="kf-prof-{{ $key }}"
           class="flex items-center justify-between gap-4 rounded-2xl ring-1 ring-gray-200/80 hover:ring-brand/30 bg-white px-5 py-4 transition hover:shadow-sm">
            <div class="min-w-0">
                <p class="font-bold text-gray-900">{{ $section['label'] }}</p>
                <p class="mt-0.5 text-xs font-medium text-gray-500">{{ $progressLabel }}</p>
            </div>
            <span class="shrink-0 text-sm font-semibold text-brand">{{ $cta }} →</span>
        </a>
    @endforeach
</section>

<section class="space-y-5">
    <div>
        <p class="text-[10px] uppercase tracking-widest font-bold text-gray-500 mb-2">{{ __('borrower.profile.hub.group_security') }}</p>
        <div class="rounded-2xl ring-1 ring-gray-200 bg-white divide-y divide-gray-100 overflow-hidden">
            <a href="{{ route('site.borrower.profile', ['section' => 'security']) }}" class="block px-4 py-3.5 text-sm font-semibold text-gray-900">{{ __('borrower.profile.security') }}</a>
            <a href="{{ route('site.borrower.settings') }}" class="block px-4 py-3.5 text-sm font-semibold text-gray-900">{{ __('borrower.nav.settings') }}</a>
        </div>
    </div>
    <div>
        <p class="text-[10px] uppercase tracking-widest font-bold text-gray-500 mb-2">{{ __('borrower.profile.hub.group_kopafasta') }}</p>
        <div class="rounded-2xl ring-1 ring-gray-200 bg-white divide-y divide-gray-100 overflow-hidden">
            <a href="{{ route('site.borrower.engagement', ['tab' => 'referrals']) }}" class="block px-4 py-3.5 text-sm font-semibold text-gray-900">{{ __('borrower.nav.referrals') }}</a>
            <a href="{{ route('site.borrower.engagement', ['tab' => 'rewards']) }}" class="block px-4 py-3.5 text-sm font-semibold text-gray-900">{{ __('borrower.nav.rewards') }}</a>
            <a href="{{ route('site.borrower.guarantors') }}" class="block px-4 py-3.5 text-sm font-semibold text-gray-900">{{ __('borrower.loans_page.tab_guarantor_requests') }}</a>
        </div>
    </div>
    <div>
        <p class="text-[10px] uppercase tracking-widest font-bold text-gray-500 mb-2">{{ __('borrower.profile.hub.group_help') }}</p>
        <div class="rounded-2xl ring-1 ring-gray-200 bg-white divide-y divide-gray-100 overflow-hidden">
            <a href="{{ route('site.borrower.support') }}" class="block px-4 py-3.5 text-sm font-semibold text-gray-900">{{ __('borrower.layout.help_center') }}</a>
            <a href="{{ route('site.faq') }}" class="block px-4 py-3.5 text-sm font-semibold text-gray-900">{{ __('borrower.layout.help') }}</a>
        </div>
    </div>
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
