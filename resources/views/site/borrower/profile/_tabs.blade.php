@props(['active' => 'personal', 'customer' => null])

@php
    $tabs = [
        'personal'  => [__('borrower.profile.personal'), 'site.borrower.profile', ['section' => 'personal']],
        'activity'  => [__('borrower.profile.activity'), 'site.borrower.profile', ['section' => 'activity']],
        'residence' => [__('borrower.profile.residence'), 'site.borrower.profile', ['section' => 'residence']],
        'payment'   => [__('borrower.payment_details.tab'), 'site.borrower.profile', ['section' => 'payment']],
        'assets'    => [__('borrower.profile.my_collaterals'), 'site.borrower.profile', ['section' => 'assets']],
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
        <x-site.bottom-sheet :title="__('borrower.profile.hub.sections_title')" open="sectionsOpen">
            <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                @foreach ($tabs as $key => [$label, $route, $params])
                    @php
                        $isActive = $active === $key || ($active === 'kyc' && $key === 'activity');
                        $isComplete = (bool) ($tabStatuses[$key]['complete'] ?? false);
                    @endphp
                    <a href="{{ route($route, $params) }}"
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

    <nav class="hidden lg:flex gap-2 overflow-x-auto snap-x snap-mandatory scrollbar-none pb-1 -mx-1 px-1 scroll-smooth" aria-label="{{ __('borrower.profile.account_nav') }}">
        @foreach ($tabs as $key => [$label, $route, $params])
            @php
                $isActive = $active === $key || ($active === 'kyc' && $key === 'activity');
                $isComplete = (bool) ($tabStatuses[$key]['complete'] ?? false);
                $inactiveRing = $isComplete
                    ? 'ring-emerald-300/90 bg-emerald-50/90 text-emerald-900'
                    : 'bg-white/80 text-gray-600 ring-gray-200/80 hover:bg-brand-muted/40';
            @endphp
            <a href="{{ route($route, $params) }}"
               class="snap-start shrink-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-sm font-semibold transition
                      {{ $isActive ? 'bg-brand text-white shadow-sm ring-2 ring-brand' : $inactiveRing }}">
                <span @class([
                    'size-2 rounded-full shrink-0',
                    $isComplete ? 'bg-emerald-500' : 'bg-gray-300',
                    $isActive ? 'ring-2 ring-white/50' : '',
                ])></span>
                <span>{{ $label }}</span>
                @if ($isComplete && ! $isActive)
                    <svg class="size-3.5 text-emerald-600 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                @endif
            </a>
        @endforeach
    </nav>
</div>
