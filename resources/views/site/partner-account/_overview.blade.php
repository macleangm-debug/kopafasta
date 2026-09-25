@props(['partner', 'profileRoute', 'portal' => null])

@php
    $service = app(\App\Services\PartnerProfileService::class);
    $sections = $service->hubCards($partner, $profileRoute);
    $settingsRoute = match ($portal) {
        'supplier' => 'site.supplier.settings',
        'affiliate' => 'site.affiliate.settings',
        'investor' => 'site.investor.settings',
        default => 'site.partner.settings',
    };
    $supportRoute = match ($portal) {
        'investor' => 'site.investor.support',
        'supplier' => \Illuminate\Support\Facades\Route::has('site.supplier.support') ? 'site.supplier.support' : null,
        'affiliate' => \Illuminate\Support\Facades\Route::has('site.affiliate.support') ? 'site.affiliate.support' : null,
        default => \Illuminate\Support\Facades\Route::has('site.partner.support') ? 'site.partner.support' : null,
    };
    $menuGroups = [
        [
            'title' => __('borrower.profile.hub.group_security'),
            'items' => [
                ['href' => route($settingsRoute), 'label' => __('borrower.profile.security'), 'icon' => '🔒'],
                ['href' => route($settingsRoute), 'label' => __('site.partner_portal.nav_settings'), 'icon' => '⚙️'],
            ],
        ],
        [
            'title' => __('borrower.profile.hub.group_help'),
            'items' => [
                [
                    'href' => $supportRoute ? route($supportRoute) : route('site.support'),
                    'label' => __('site.partner_portal.nav_support'),
                    'icon' => '💬',
                ],
            ],
        ],
    ];
@endphp

<section class="mb-6">
    <p class="text-[10px] uppercase tracking-widest font-bold text-gray-500 mb-2">{{ __('site.partner_account.sections_title') }}</p>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
        @foreach ($sections as $section)
            @php
                $isComplete = ($section['status'] ?? '') === 'complete';
                $progress = $section['progress'] ?? null;
                $remaining = (int) ($progress['remaining'] ?? count($section['missing'] ?? []));
                $done = (int) ($progress['done'] ?? 0);
                $isEmpty = $done === 0 && ! $isComplete;

                if ($isComplete) {
                    $progressLabel = ! empty($progress['total'])
                        ? __('borrower.profile.hub.of_complete', [
                            'done' => $progress['total'],
                            'total' => $progress['total'],
                        ])
                        : __('borrower.profile.status.complete');
                    $cta = __('borrower.profile.status.complete');
                    $ctaTone = 'done';
                    $showTick = true;
                } elseif ($progress && (int) ($progress['total'] ?? 0) > 0) {
                    $progressLabel = __('borrower.profile.hub.of_complete', [
                        'done' => $progress['done'],
                        'total' => $progress['total'],
                    ]);
                    $cta = $isEmpty
                        ? __('borrower.profile.hub.add')
                        : __('borrower.profile.hub.continue');
                    $ctaTone = $isEmpty ? 'add' : 'continue';
                    $showTick = false;
                } else {
                    $progressLabel = $section['description'] ?? __('borrower.profile.hub.of_complete', ['done' => 0, 'total' => 1]);
                    $cta = $section['action_label'] ?? __('borrower.profile.hub.add');
                    $ctaTone = 'add';
                    $showTick = false;
                }
            @endphp
            <a href="{{ $section['url'] }}"
               data-kf-share="kf-psec-{{ $section['key'] }}"
               class="group rounded-2xl ring-1 ring-gray-200/80 hover:ring-brand/30 bg-white px-3.5 py-3 transition hover:shadow-md flex items-center gap-3">
                <span class="text-2xl leading-none shrink-0" aria-hidden="true">{{ $section['icon'] ?? '📋' }}</span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-gray-900 group-hover:text-brand transition leading-snug">{{ $section['label'] }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $progressLabel }}</p>
                    @if (! $isComplete && $remaining > 0)
                        <p class="mt-0.5 text-xs font-semibold text-amber-800">
                            {{ trans_choice('borrower.profile.hub.remaining_count', $remaining, ['count' => $remaining]) }}
                        </p>
                    @endif
                </div>
                @if ($showTick)
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

<section class="space-y-5">
    @foreach ($menuGroups as $group)
        <div>
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
        </div>
    @endforeach
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
