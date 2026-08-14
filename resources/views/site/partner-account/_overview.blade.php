@props(['partner', 'profileRoute'])

@php
    $service = app(\App\Services\PartnerProfileService::class);
    $sections = $service->hubCards($partner, $profileRoute);
    $percent = $service->completionPercent($partner);

    $statusColors = [
        'complete'     => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        'in_progress'  => 'bg-sky-100 text-sky-800 ring-sky-200',
        'not_started'  => 'bg-gray-100 text-gray-600 ring-gray-200',
    ];
@endphp

<section class="mb-6 glass-card overflow-hidden relative">
    <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_rgba(245,200,66,0.35),_transparent_55%)] pointer-events-none"></div>
    <div class="relative p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <div>
                <h2 class="font-bold text-gray-900 text-lg">{{ __('site.partner_account.hub_title') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('site.partner_account.hub_subtitle') }}</p>
            </div>
            <div class="text-right shrink-0">
                <p class="text-3xl font-bold text-brand tabular-nums leading-none">{{ $percent }}%</p>
            </div>
        </div>
        <div class="relative h-3 rounded-full bg-gray-200/80 overflow-hidden">
            <div class="absolute inset-y-0 left-0 rounded-full transition-all duration-500 {{ $percent >= 100 ? 'bg-emerald-500' : 'bg-gradient-to-r from-brand to-brand-gold' }}"
                 style="width: {{ max(2, $percent) }}%"></div>
        </div>
    </div>
</section>

<section class="mb-6">
    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('site.partner_account.sections_title') }}</p>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($sections as $section)
            @php
                $status = $section['status'] ?? 'not_started';
                $tagClass = $statusColors[$status] ?? $statusColors['not_started'];
            @endphp
            <a href="{{ $section['url'] }}"
               data-kf-share="kf-psec-{{ $section['key'] }}"
               class="group rounded-2xl ring-1 ring-gray-200/80 hover:ring-brand/30 bg-white p-5 transition hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <span class="text-2xl leading-none" aria-hidden="true">{{ $section['icon'] ?? '📋' }}</span>
                    <span class="inline-flex items-center gap-1.5">
                        @if (($section['status'] ?? '') === 'complete')
                            <span class="size-7 rounded-full grid place-items-center bg-gradient-to-br from-brand to-brand-light text-brand-gold shadow-sm ring-2 ring-brand-gold/40"
                                  title="{{ __('borrower.profile.section_complete') }}"
                                  aria-label="{{ __('borrower.profile.section_complete') }}">
                                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        @endif
                        <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full px-2.5 py-1 ring-1 {{ $tagClass }}">
                            {{ $section['status_label'] ?? $status }}
                        </span>
                    </span>
                </div>
                <h3 class="mt-4 font-bold text-gray-900 group-hover:text-brand transition">{{ $section['label'] }}</h3>
                @if (! empty($section['description']))
                    <p class="text-xs text-gray-500 mt-1 line-clamp-3">{{ $section['description'] }}</p>
                @endif
                <p class="mt-4 text-xs font-semibold text-brand">{{ $section['action_label'] ?? __('borrower.profile.hub.view_edit') }} →</p>
            </a>
        @endforeach
    </div>
</section>
