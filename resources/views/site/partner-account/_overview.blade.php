@props(['partner', 'profileRoute', 'portal' => null])

@php
    $service = app(\App\Services\PartnerProfileService::class);
    $sections = $service->hubCards($partner, $profileRoute);
    $extraGroups = [];
    if ($portal === 'supplier') {
        $extraGroups[] = [
            'title' => __('site.supplier_portal.profile_title'),
            'items' => [
                [
                    'href' => route($profileRoute, ['section' => 'card']),
                    'label' => __('site.supplier_portal.nav_card'),
                    'icon' => '🪪',
                ],
                [
                    'href' => route($profileRoute, ['section' => 'documents']),
                    'label' => __('site.supplier_portal.tab_documents'),
                    'icon' => '📁',
                ],
                [
                    'href' => route('site.supplier.settings'),
                    'label' => __('site.supplier_portal.tab_security'),
                    'icon' => '⚙️',
                ],
            ],
        ];
    }
@endphp

<section class="mb-6">
    <p class="text-[10px] uppercase tracking-widest font-bold text-gray-500 mb-2">{{ __('site.partner_account.sections_title') }}</p>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
        @foreach ($sections as $section)
            @php
                $isComplete = ($section['status'] ?? '') === 'complete';
                $cta = $section['action_label'] ?? ($isComplete ? __('borrower.profile.hub.view_edit') : __('borrower.profile.hub.add'));
                $ctaTone = $isComplete ? 'done' : 'add';
            @endphp
            <a href="{{ $section['url'] }}"
               data-kf-share="kf-psec-{{ $section['key'] }}"
               class="group rounded-2xl ring-1 ring-gray-200/80 hover:ring-brand/30 bg-white px-3.5 py-3 transition hover:shadow-md flex items-center gap-3">
                <span class="text-2xl leading-none shrink-0" aria-hidden="true">{{ $section['icon'] ?? '📋' }}</span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-gray-900 group-hover:text-brand transition leading-snug">{{ $section['label'] }}</h3>
                    @if (! empty($section['description']))
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $section['description'] }}</p>
                    @endif
                </div>
                @if ($isComplete)
                    <span class="size-6 rounded-full grid place-items-center bg-gradient-to-br from-brand to-brand-light text-brand-gold shadow-sm ring-2 ring-brand-gold/40 shrink-0"
                          title="{{ __('borrower.profile.section_complete') }}"
                          aria-label="{{ __('borrower.profile.section_complete') }}">
                        <svg class="size-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                @else
                    <span @class([
                        'shrink-0 inline-flex items-center justify-center rounded-full px-3 py-1.5 text-xs font-bold',
                        'bg-brand text-white shadow-sm' => $ctaTone === 'add',
                        'bg-white text-brand ring-1 ring-brand/25' => $ctaTone !== 'add',
                    ])>
                        {{ $cta }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>
</section>

@foreach ($extraGroups as $group)
    <section class="mb-6">
        <p class="text-[10px] uppercase tracking-widest font-bold text-gray-500 mb-2">{{ $group['title'] }}</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach ($group['items'] as $item)
                <a href="{{ $item['href'] }}"
                   class="rounded-2xl ring-1 ring-gray-200/80 bg-white px-3.5 py-3 hover:ring-brand/30 hover:shadow-sm transition flex items-center gap-3">
                    <span class="text-2xl leading-none select-none shrink-0" aria-hidden="true">{{ $item['icon'] }}</span>
                    <span class="min-w-0 flex-1 font-semibold text-sm text-gray-900 leading-snug">{{ $item['label'] }}</span>
                    <span class="text-brand shrink-0 text-base font-bold" aria-hidden="true">→</span>
                </a>
            @endforeach
        </div>
    </section>
@endforeach
