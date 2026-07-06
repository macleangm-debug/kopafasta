@props(['customer'])

@php
    $service = app(\App\Services\ProfileCompletionService::class);
    $calc = $service->calculate($customer);
    $percent = (int) ($calc['percent'] ?? 0);
    $threshold = (int) ($calc['threshold'] ?? 60);
    $tabStatuses = $service->tabStatuses($customer);
    $meetsThreshold = $percent >= 100 || $percent >= $threshold;

    $sectionMeta = [
        'personal'  => ['icon' => '👤', 'hint' => ''],
        'activity'  => ['icon' => '💼', 'hint' => ''],
        'residence' => ['icon' => '🏠', 'hint' => ''],
        'kyc'       => ['icon' => '📄', 'hint' => ''],
        'security'  => ['icon' => '🔒', 'hint' => ''],
        'payment'   => ['icon' => '💳', 'hint' => ''],
        'assets'    => ['icon' => '🚗', 'hint' => ''],
    ];
@endphp

<section class="mb-6 glass-card overflow-hidden relative">
    <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_rgba(245,200,66,0.35),_transparent_55%)] pointer-events-none"></div>

    <div class="relative p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <div>
                <h2 class="font-bold text-gray-900 text-lg">{{ __('borrower.profile.completion_hub_title') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('borrower.profile.completion_hub_subtitle') }}</p>
            </div>
            <div class="text-right shrink-0">
                <p class="text-3xl font-bold text-brand tabular-nums leading-none">{{ $percent }}%</p>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 mt-1">{{ __('borrower.profile.completion_label') }}</p>
            </div>
        </div>

        <div class="relative h-3 rounded-full bg-gray-200/80 overflow-hidden">
            <div class="absolute inset-y-0 left-0 rounded-full transition-all duration-500 {{ $percent >= 100 ? 'bg-emerald-500' : 'bg-gradient-to-r from-brand to-brand-gold' }}"
                 style="width: {{ max(2, $percent) }}%"></div>
        </div>
        <div class="flex justify-between mt-2 text-[10px] uppercase tracking-wide text-gray-500 font-semibold">
            <span>0%</span>
            <span>{{ __('borrower.profile.completion_threshold_short', ['percent' => $threshold]) }}</span>
            <span>100%</span>
        </div>

        @unless ($meetsThreshold)
            <p class="mt-4 text-sm text-amber-800 font-medium">{{ __('borrower.profile.completion_threshold_hint', ['percent' => $threshold]) }}</p>
        @else
            <p class="mt-4 text-sm text-emerald-700 font-medium">{{ __('borrower.profile.section_complete') }}</p>
        @endunless
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
                @if (filled($meta['hint']))
                    <p class="mt-1 text-xs text-gray-500 line-clamp-2">{{ $meta['hint'] }}</p>
                @endif
                <p class="mt-4 text-xs font-semibold text-brand">
                    {{ $complete ? __('borrower.profile.hub.view_edit') : __('borrower.profile.hub.complete_section') }} →
                </p>
            </a>
        @endforeach
    </div>
</section>
