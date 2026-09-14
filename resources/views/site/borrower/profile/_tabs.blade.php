@props(['active' => 'personal', 'customer' => null])

@php
    $tabs = [
        'personal'  => [__('borrower.profile.hub.layperson.about_you'), 'site.borrower.profile', ['section' => 'personal']],
        'activity'  => [__('borrower.profile.hub.layperson.work_money'), 'site.borrower.profile', ['section' => 'activity']],
        'residence' => [__('borrower.profile.hub.layperson.where_you_live'), 'site.borrower.profile', ['section' => 'residence']],
        'payment'   => [__('borrower.profile.hub.layperson.payment_accounts'), 'site.borrower.profile', ['section' => 'payment']],
        'assets'    => [__('borrower.profile.hub.layperson.your_assets'), 'site.borrower.profile', ['section' => 'assets']],
    ];
    $tabStatuses = $customer
        ? app(\App\Services\ProfileCompletionService::class)->tabStatuses($customer)
        : [];
    $activeLabel = $tabs[$active][0] ?? ($tabs['personal'][0] ?? __('borrower.profile.hub.sections_title'));
@endphp

<div class="mb-6" x-data="{ sectionsOpen: false }">
    <div class="lg:hidden">
        <button type="button" @click="sectionsOpen = true"
                class="w-full inline-flex items-center justify-between gap-3 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition">
            <span class="inline-flex items-center gap-2 min-w-0">
                <svg class="w-4 h-4 text-brand shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                <span class="truncate">{{ $activeLabel }}</span>
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
                    @endphp
                    <a href="{{ route($route, $params) }}"
                       data-kf-motion="tab"
                       class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl text-sm font-semibold {{ $isActive ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : 'text-gray-800 hover:bg-gray-50' }}">
                        <span class="inline-flex items-center gap-2">
                            <span @class([
                                'size-2 rounded-full shrink-0',
                                $isComplete ? 'bg-emerald-500' : 'bg-gray-300',
                            ])></span>
                            <span>{{ $label }}</span>
                        </span>
                        @if ($isActive)
                            <svg class="size-4 text-brand shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        @elseif ($isComplete)
                            <svg class="size-4 text-emerald-600 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-label="{{ __('borrower.profile.section_complete') }}"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        @endif
                    </a>
                @endforeach
            </div>
        </x-site.bottom-sheet>
    </div>

    {{-- Desktop: premium dropdown (not a giant horizontal stepper of every section). --}}
    <div class="hidden lg:block relative" @click.outside="sectionsOpen = false">
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('site.borrower.profile') }}" class="text-sm font-semibold text-brand hover:underline">← {{ __('borrower.profile.hub.back') }}</a>
            <button type="button" @click="sectionsOpen = !sectionsOpen"
                    class="inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:ring-brand/30 transition">
                <span class="truncate max-w-[14rem]">{{ $activeLabel }}</span>
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
                @endphp
                <a href="{{ route($route, $params) }}"
                   data-kf-motion="tab"
                   class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold {{ $isActive ? 'bg-brand-muted text-brand' : 'text-gray-800 hover:bg-gray-50' }}">
                    <span class="inline-flex items-center gap-2 min-w-0">
                        <span @class(['size-2 rounded-full shrink-0', $isComplete ? 'bg-emerald-500' : 'bg-gray-300'])></span>
                        <span class="truncate">{{ $label }}</span>
                    </span>
                    @if ($isComplete)
                        <svg class="size-3.5 text-emerald-600 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
