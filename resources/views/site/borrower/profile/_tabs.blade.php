@props(['active' => 'personal', 'customer' => null])

@php
    $tabs = [
        'personal'  => [__('borrower.profile.hub.layperson.about_you'), 'site.borrower.profile', ['section' => 'personal']],
        'activity'  => [__('borrower.profile.hub.layperson.work_money'), 'site.borrower.profile', ['section' => 'activity']],
        'residence' => [__('borrower.profile.hub.layperson.where_you_live'), 'site.borrower.profile', ['section' => 'residence']],
        'payment'   => [__('borrower.profile.hub.layperson.payment_accounts'), 'site.borrower.profile', ['section' => 'payment']],
        'assets'    => [__('borrower.profile.hub.layperson.your_assets'), 'site.borrower.profile', ['section' => 'assets']],
    ];
    $completion = $customer
        ? app(\App\Services\ProfileCompletionService::class)
        : null;
    $tabStatuses = $customer && $completion
        ? $completion->tabStatuses($customer)
        : [];
    $tabRemaining = [];
    if ($customer && $completion) {
        foreach (array_keys($tabs) as $key) {
            if ($key === 'assets') {
                $tabRemaining[$key] = 0;
                continue;
            }
            $gapKey = $key === 'activity' ? 'activity' : $key;
            $tabRemaining[$key] = count($completion->sectionGaps($customer, $gapKey));
        }
    }
    $activeLabel = $tabs[$active][0] ?? ($tabs['personal'][0] ?? __('borrower.profile.hub.sections_title'));
    $activeRemaining = (int) ($tabRemaining[$active] ?? 0);
    $activeComplete = (bool) ($tabStatuses[$active]['complete'] ?? false);
@endphp

<div class="mb-6" x-data="{ sectionsOpen: false }">
    <div class="lg:hidden">
        <button type="button" @click="sectionsOpen = true"
                class="w-full inline-flex items-center justify-between gap-3 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition">
            <span class="inline-flex items-center gap-2 min-w-0">
                <svg class="w-4 h-4 text-brand shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                <span class="truncate">{{ $activeLabel }}</span>
                @if ($activeComplete)
                    <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ __('borrower.profile.section_complete') }}</span>
                @elseif ($activeRemaining > 0)
                    <span class="text-[10px] font-bold uppercase tracking-wide text-amber-700">{{ trans_choice('borrower.profile.hub.remaining_count', $activeRemaining, ['count' => $activeRemaining]) }}</span>
                @endif
            </span>
            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>
        <x-site.bottom-sheet :title="__('borrower.profile.hub.switch_section')" open="sectionsOpen">
            <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                <a href="{{ route('site.borrower.profile') }}"
                   class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-gray-800 hover:bg-gray-50">
                    <span>{{ __('borrower.profile.hub.back') }}</span>
                </a>
                @foreach ($tabs as $key => [$label, $route, $params])
                    @php
                        $isActive = $active === $key || ($active === 'kyc' && $key === 'activity');
                        $isComplete = (bool) ($tabStatuses[$key]['complete'] ?? false);
                        $remaining = (int) ($tabRemaining[$key] ?? 0);
                    @endphp
                    <a href="{{ route($route, $params) }}"
                       data-kf-motion="tab"
                       class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl text-sm font-semibold {{ $isActive ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : 'text-gray-800 hover:bg-gray-50' }}">
                        <span class="inline-flex items-center gap-2 min-w-0">
                            <span @class([
                                'size-2 rounded-full shrink-0',
                                $isComplete ? 'bg-emerald-500' : 'bg-amber-400',
                            ])></span>
                            <span class="truncate">{{ $label }}</span>
                        </span>
                        @if ($isComplete)
                            <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ __('borrower.profile.section_complete') }}</span>
                        @elseif ($remaining > 0)
                            <span class="text-[10px] font-bold uppercase tracking-wide text-amber-700">{{ trans_choice('borrower.profile.hub.remaining_count', $remaining, ['count' => $remaining]) }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </x-site.bottom-sheet>
    </div>

    {{-- Desktop: premium collapsible category switcher --}}
    <div class="hidden lg:block relative" @click.outside="sectionsOpen = false">
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('site.borrower.profile') }}" class="text-sm font-semibold text-brand hover:underline">← {{ __('borrower.profile.hub.back') }}</a>
            <button type="button" @click="sectionsOpen = !sectionsOpen"
                    class="inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition">
                <span class="truncate max-w-[14rem]">{{ $activeLabel }}</span>
                @if ($activeComplete)
                    <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ __('borrower.profile.section_complete') }}</span>
                @elseif ($activeRemaining > 0)
                    <span class="text-[10px] font-bold uppercase tracking-wide text-amber-700">{{ trans_choice('borrower.profile.hub.remaining_count', $activeRemaining, ['count' => $activeRemaining]) }}</span>
                @endif
                <svg class="w-4 h-4 text-gray-400 shrink-0 transition" :class="sectionsOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
        </div>
        <div x-show="sectionsOpen" x-cloak x-transition
             class="absolute right-0 z-20 mt-2 w-80 rounded-2xl bg-white shadow-xl ring-1 ring-brand/10 p-2">
            <p class="px-3 py-2 text-[10px] uppercase tracking-widest font-bold text-gray-500">{{ __('borrower.profile.hub.switch_section') }}</p>
            @foreach ($tabs as $key => [$label, $route, $params])
                @php
                    $isActive = $active === $key || ($active === 'kyc' && $key === 'activity');
                    $isComplete = (bool) ($tabStatuses[$key]['complete'] ?? false);
                    $remaining = (int) ($tabRemaining[$key] ?? 0);
                @endphp
                <a href="{{ route($route, $params) }}"
                   data-kf-motion="tab"
                   class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold {{ $isActive ? 'bg-brand-muted text-brand' : 'text-gray-800 hover:bg-gray-50' }}">
                    <span class="inline-flex items-center gap-2 min-w-0">
                        <span @class(['size-2 rounded-full shrink-0', $isComplete ? 'bg-emerald-500' : 'bg-amber-400'])></span>
                        <span class="truncate">{{ $label }}</span>
                    </span>
                    @if ($isComplete)
                        <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ __('borrower.profile.section_complete') }}</span>
                    @elseif ($remaining > 0)
                        <span class="text-[10px] font-bold uppercase tracking-wide text-amber-700">{{ trans_choice('borrower.profile.hub.remaining_count', $remaining, ['count' => $remaining]) }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
