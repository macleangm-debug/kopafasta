@props(['active' => 'personal', 'partner' => null, 'profileRoute'])

@php
    $service = app(\App\Services\PartnerProfileService::class);
    $tabs = [
        'personal'  => __('site.partner_account.tab_personal'),
        'face'      => __('site.partner_account.tab_face'),
        'residence' => __('site.partner_account.tab_residence'),
        'activity'  => __('site.partner_account.tab_activity'),
        'payment'   => __('site.partner_account.tab_payment'),
    ];
@endphp

<nav class="flex gap-2 mb-6 overflow-x-auto snap-x snap-mandatory scrollbar-none pb-1 -mx-1 px-1 scroll-smooth" aria-label="{{ __('site.partner_account.sections_title') }}">
    @foreach ($tabs as $key => $label)
        @php
            $isActive = $active === $key;
            $status = $partner ? $service->sectionStatus($partner, $key) : null;
            $isComplete = $status['complete'] ?? null;
            $inactiveRing = $isComplete === true
                ? 'ring-emerald-300/90 bg-emerald-50/90 text-emerald-900'
                : 'bg-white/80 text-gray-600 ring-gray-200/80 hover:bg-brand-muted/40';
        @endphp
        <a href="{{ route($profileRoute, ['section' => $key]) }}"
           class="snap-start shrink-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-sm font-semibold transition
                  {{ $isActive ? 'bg-brand text-white shadow-sm ring-2 ring-brand' : $inactiveRing }}">
            @if ($status !== null)
                <span @class([
                    'size-2 rounded-full shrink-0',
                    $isComplete ? 'bg-emerald-500' : 'bg-gray-300',
                    $isActive ? 'ring-2 ring-white/50' : '',
                ])></span>
            @endif
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
