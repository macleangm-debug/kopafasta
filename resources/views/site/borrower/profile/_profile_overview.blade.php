@props(['customer'])

@php
    $service = app(\App\Services\ProfileCompletionService::class);
    $calc = $service->calculate($customer);
    $percent = (int) ($calc['percent'] ?? 0);
    $threshold = (int) ($calc['threshold'] ?? 60);
    $tabStatuses = $service->tabStatuses($customer);
    $ringRadius = 52;
    $ringCircumference = 2 * M_PI * $ringRadius;
    $ringDashoffset = $ringCircumference - ($percent / 100) * $ringCircumference;
    $meetsThreshold = $percent >= 100 || $percent >= $threshold;

    $sectionMeta = [
        'personal'  => ['icon' => '👤', 'hint' => __('borrower.profile.hub.personal_hint')],
        'activity'  => ['icon' => '💼', 'hint' => __('borrower.profile.hub.activity_hint')],
        'residence' => ['icon' => '🏠', 'hint' => __('borrower.profile.hub.residence_hint')],
        'kyc'       => ['icon' => '📄', 'hint' => __('borrower.profile.hub.kyc_hint')],
        'security'  => ['icon' => '🔒', 'hint' => __('borrower.profile.hub.security_hint')],
        'payment'   => ['icon' => '💳', 'hint' => __('borrower.profile.hub.payment_hint')],
        'assets'    => ['icon' => '🚗', 'hint' => __('borrower.profile.hub.assets_hint')],
    ];
@endphp

<section class="mb-6 glass-card overflow-hidden relative">
    <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_rgba(245,200,66,0.35),_transparent_55%)] pointer-events-none"></div>

    <div class="relative p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-6">
            <div class="relative size-28 sm:size-32 shrink-0 mx-auto sm:mx-0">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 128 128">
                    <circle cx="64" cy="64" r="{{ $ringRadius }}" stroke-width="10" class="stroke-gray-200/80" fill="none"></circle>
                    <circle cx="64" cy="64" r="{{ $ringRadius }}" stroke-width="10"
                            class="{{ $percent >= 100 ? 'stroke-emerald-500' : 'stroke-brand' }}"
                            fill="none" stroke-linecap="round"
                            stroke-dasharray="{{ format_number($ringCircumference, 2, '.', '') }}"
                            stroke-dashoffset="{{ format_number($ringDashoffset, 2, '.', '') }}"></circle>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-3xl font-bold text-gray-900 leading-none tabular-nums">{{ $percent }}%</span>
                    <span class="text-[10px] uppercase tracking-wide text-gray-500 mt-1">{{ __('borrower.profile.completion_hub_title') }}</span>
                </div>
            </div>
            <div class="flex-1 text-center sm:text-left">
                <h2 class="font-bold text-gray-900 text-lg">{{ __('borrower.profile.completion_hub_title') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('borrower.profile.completion_hub_subtitle') }}</p>
                @unless ($meetsThreshold)
                    <p class="mt-3 text-sm text-amber-800 font-medium">{{ __('borrower.profile.completion_threshold_hint', ['percent' => $threshold]) }}</p>
                @else
                    <p class="mt-3 text-sm text-emerald-700 font-medium">{{ __('borrower.profile.section_complete') }}</p>
                @endunless
            </div>
        </div>
    </div>
</section>

<section class="mb-6">
    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('borrower.profile.hub.sections_title') }}</p>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($tabStatuses as $key => $tab)
            @php
                $complete = (bool) ($tab['complete'] ?? false);
                $required = (bool) ($tab['required'] ?? false);
                $meta = $sectionMeta[$key] ?? ['icon' => '📋', 'hint' => ''];
                $statusLabel = $complete
                    ? __('borrower.profile.tab_complete')
                    : ($required ? __('borrower.profile.tab_incomplete') : __('borrower.profile.tab_optional'));
                $tagClass = $complete
                    ? 'bg-emerald-100 text-emerald-800 ring-emerald-200'
                    : ($required ? 'bg-amber-100 text-amber-900 ring-amber-200' : 'bg-gray-100 text-gray-600 ring-gray-200');
                $cardClass = $complete
                    ? 'ring-emerald-200/80 hover:ring-emerald-300 bg-gradient-to-br from-emerald-50/80 to-white'
                    : ($required ? 'ring-amber-200/80 hover:ring-amber-300 bg-gradient-to-br from-amber-50/40 to-white' : 'ring-gray-200/80 hover:ring-brand/30 bg-white');
            @endphp
            <a href="{{ $tab['url'] }}"
               class="group rounded-2xl ring-1 p-5 transition hover:shadow-md {{ $cardClass }}">
                <div class="flex items-start justify-between gap-3">
                    <span class="text-2xl leading-none" aria-hidden="true">{{ $meta['icon'] }}</span>
                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full px-2.5 py-1 ring-1 {{ $tagClass }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                <h3 class="mt-4 font-bold text-gray-900 group-hover:text-brand transition">{{ $tab['label'] }}</h3>
                <p class="mt-1 text-xs text-gray-500 line-clamp-2">{{ $meta['hint'] }}</p>
                <p class="mt-4 text-xs font-semibold text-brand">
                    {{ $complete ? __('borrower.profile.hub.view_edit') : __('borrower.profile.hub.complete_section') }} →
                </p>
            </a>
        @endforeach
    </div>
</section>
