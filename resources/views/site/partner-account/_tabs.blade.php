@props(['active' => 'personal', 'partner' => null, 'profileRoute', 'portal' => null])

@php
    $service = app(\App\Services\PartnerProfileService::class);
    $sectionKeys = $partner ? $service->sectionsFor($partner) : ['personal', 'face', 'residence', 'payment'];
    $labels = [
        'hub'       => __('site.supplier_portal.tab_overview'),
        'personal'  => $portal === 'supplier'
            ? __('site.supplier_portal.tab_contact')
            : __('site.partner_account.tab_personal'),
        'company'   => $portal === 'supplier'
            ? __('site.supplier_portal.tab_business')
            : __('site.partner_account.tab_company'),
        'face'      => __('site.partner_account.tab_face'),
        'residence' => $portal === 'supplier'
            ? __('site.supplier_portal.tab_address')
            : (($partner instanceof \App\Models\Partner && $partner->isCompanyApplicant())
                ? __('site.partner_account.tab_company_address')
                : __('site.partner_account.tab_residence')),
        'activity'  => __('site.partner_account.tab_activity'),
        'payment'   => $portal === 'supplier'
            ? __('site.supplier_portal.tab_payment')
            : __('site.partner_account.tab_payment'),
        'documents' => __('site.supplier_portal.tab_documents'),
        'settings'  => __('site.supplier_portal.tab_security'),
        'agreement' => __('site.affiliate_portal.agreement_title'),
        'membership'=> __('site.affiliate_portal.membership_title'),
    ];
    $tabs = collect($sectionKeys)
        ->mapWithKeys(fn (string $key) => [$key => $labels[$key] ?? $key])
        ->all();
    if ($portal === 'supplier') {
        $tabs = ['hub' => $labels['hub']] + $tabs;
        $tabs['documents'] = $labels['documents'];
        $tabs['settings'] = $labels['settings'];
    }
    if ($portal === 'affiliate' && $partner instanceof \App\Models\Partner && $partner->isAffiliate()) {
        if ($partner->isPremiumAffiliate()) {
            $tabs['agreement'] = $labels['agreement'];
        } else {
            $tabs['membership'] = $labels['membership'];
        }
    }
    $profileKeys = $partner ? $service->sectionsFor($partner) : [];
    $tabRemaining = [];
    foreach (array_keys($tabs) as $key) {
        $tabRemaining[$key] = ($partner && in_array($key, $profileKeys, true))
            ? count($service->sectionGaps($partner, $key))
            : 0;
    }
    $activeLabel = $tabs[$active] ?? __('site.partner_account.sections_title');
    $activeStatus = ($partner && $active !== 'hub') ? $service->sectionStatus($partner, $active) : null;
    $activeComplete = (bool) ($activeStatus['complete'] ?? false);
    $activeRemaining = (int) ($tabRemaining[$active] ?? 0);
    $activeGaps = ($partner && in_array($active, $profileKeys, true))
        ? $service->sectionGaps($partner, $active)
        : [];
    $hubUrl = route($profileRoute);
    $completeLabel = __('borrower.profile.section_complete');
@endphp

<div class="mb-6" x-data="{ sectionsOpen: false, remainingOpen: false }">
    <div class="lg:hidden">
        <button type="button" @click="sectionsOpen = true"
                class="w-full inline-flex items-center justify-between gap-3 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition">
            <span class="inline-flex items-center gap-2 min-w-0">
                <svg class="w-4 h-4 text-brand shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                <span class="truncate">{{ $activeLabel }}</span>
                @if ($activeComplete)
                    <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ $completeLabel }}</span>
                @elseif ($activeRemaining > 0)
                    <span class="text-[10px] font-bold uppercase tracking-wide text-amber-700">
                        {{ trans_choice('borrower.profile.hub.remaining_count', $activeRemaining, ['count' => $activeRemaining]) }}
                    </span>
                @endif
            </span>
            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </button>
        <x-site.bottom-sheet :title="__('borrower.profile.hub.switch_section')" open="sectionsOpen">
            <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                @if ($active !== 'hub')
                    <a href="{{ $hubUrl }}"
                       data-kf-motion="tab"
                       class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-gray-800 hover:bg-gray-50">
                        <span>{{ __('borrower.profile.hub.back') }}</span>
                    </a>
                @endif
                @foreach ($tabs as $key => $label)
                    @php
                        $isActive = $active === $key;
                        $status = $partner ? $service->sectionStatus($partner, $key) : null;
                        $isComplete = (bool) ($status['complete'] ?? false);
                        $remaining = (int) ($tabRemaining[$key] ?? 0);
                    @endphp
                    <a href="{{ route($profileRoute, ['section' => $key === 'hub' ? null : $key]) }}"
                       data-kf-motion="tab"
                       class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl text-sm font-semibold {{ $isActive ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : 'text-gray-800 hover:bg-gray-50' }}">
                        <span class="inline-flex items-center gap-2 min-w-0">
                            @if ($status !== null && in_array($key, $profileKeys, true))
                                <span @class([
                                    'size-2 rounded-full shrink-0',
                                    $isComplete ? 'bg-emerald-500' : 'bg-amber-400',
                                ])></span>
                            @endif
                            <span class="truncate">{{ $label }}</span>
                        </span>
                        @if ($isComplete && in_array($key, $profileKeys, true))
                            <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ $completeLabel }}</span>
                        @elseif ($remaining > 0)
                            <span class="text-[10px] font-bold uppercase tracking-wide text-amber-700">
                                {{ trans_choice('borrower.profile.hub.remaining_count', $remaining, ['count' => $remaining]) }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </x-site.bottom-sheet>
    </div>

    <div class="hidden lg:block relative" @click.outside="sectionsOpen = false">
        <div class="flex items-center justify-between gap-3">
            @if ($active !== 'hub')
                <a href="{{ $hubUrl }}" class="text-sm font-semibold text-brand hover:underline">← {{ __('borrower.profile.hub.back') }}</a>
            @else
                <p class="text-sm font-semibold text-gray-500">{{ __('borrower.profile.hub.switch_section') }}</p>
            @endif
            <button type="button" @click="sectionsOpen = !sectionsOpen"
                    class="inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition">
                <span class="truncate max-w-[14rem]">{{ $activeLabel }}</span>
                @if ($activeComplete)
                    <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ $completeLabel }}</span>
                @elseif ($activeRemaining > 0)
                    <span class="text-[10px] font-bold uppercase tracking-wide text-amber-700">
                        {{ trans_choice('borrower.profile.hub.remaining_count', $activeRemaining, ['count' => $activeRemaining]) }}
                    </span>
                @endif
                <svg class="w-4 h-4 text-gray-400 shrink-0 transition" :class="sectionsOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
        </div>
        <div x-show="sectionsOpen" x-cloak x-transition
             class="absolute right-0 z-20 mt-2 w-80 rounded-2xl bg-white shadow-xl ring-1 ring-brand/10 p-2">
            <p class="px-3 py-2 text-[10px] uppercase tracking-widest font-bold text-gray-500">{{ __('borrower.profile.hub.switch_section') }}</p>
            @foreach ($tabs as $key => $label)
                @php
                    $isActive = $active === $key;
                    $status = $partner ? $service->sectionStatus($partner, $key) : null;
                    $isComplete = (bool) ($status['complete'] ?? false);
                    $remaining = (int) ($tabRemaining[$key] ?? 0);
                @endphp
                <a href="{{ route($profileRoute, ['section' => $key === 'hub' ? null : $key]) }}"
                   data-kf-motion="tab"
                   class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold {{ $isActive ? 'bg-brand-muted text-brand' : 'text-gray-800 hover:bg-gray-50' }}">
                    <span class="inline-flex items-center gap-2 min-w-0">
                        @if ($status !== null && in_array($key, $profileKeys, true))
                            <span @class(['size-2 rounded-full shrink-0', $isComplete ? 'bg-emerald-500' : 'bg-amber-400'])></span>
                        @endif
                        <span class="truncate">{{ $label }}</span>
                    </span>
                    @if ($isComplete && in_array($key, $profileKeys, true))
                        <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ $completeLabel }}</span>
                    @elseif ($remaining > 0)
                        <span class="text-[10px] font-bold uppercase tracking-wide text-amber-700">
                            {{ trans_choice('borrower.profile.hub.remaining_count', $remaining, ['count' => $remaining]) }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    @if ($activeGaps !== [])
        <div class="mt-3 rounded-xl bg-white ring-1 ring-amber-200/80 overflow-hidden" data-kf-remaining-list>
            <button type="button" @click="remainingOpen = !remainingOpen"
                    class="w-full flex items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-amber-900 hover:bg-amber-50/60 transition">
                <span>{{ trans_choice('borrower.profile.hub.remaining_count', $activeRemaining, ['count' => $activeRemaining]) }}</span>
                <svg class="w-4 h-4 text-amber-700 shrink-0 transition" :class="remainingOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
            <ul x-show="remainingOpen" x-cloak class="border-t border-amber-100 divide-y divide-amber-50" data-kf-remaining-items>
                @foreach ($activeGaps as $gap)
                    <li class="px-4 py-2.5 text-sm font-medium text-gray-900">{{ $gap['label'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
