@props(['customer'])

@php
    $service = app(\App\Services\ProfileCompletionService::class);
    $calc = $service->calculate($customer);
    $percent = (int) ($calc['percent'] ?? 0);
    $threshold = (int) ($calc['threshold'] ?? 60);
    $tabStatuses = $service->tabStatuses($customer);
    $requiredTabs = collect($tabStatuses)->where('required', true);
    $requiredDone = $requiredTabs->where('complete', true)->count();
    $requiredTotal = max(1, $requiredTabs->count());
    $identityDeferred = ! $service->identityRequiredDuringProfile();
    $remainingSections = $service->displaySections($customer, true);
    $ringRadius = 52;
    $ringCircumference = 2 * M_PI * $ringRadius;
    $ringDashoffset = $ringCircumference - ($percent / 100) * $ringCircumference;
    $meetsThreshold = $percent >= $threshold;
@endphp

<section class="mb-6 glass-card overflow-hidden relative">
    <div class="absolute inset-0 opacity-40 bg-[radial-gradient(circle_at_top_right,_rgba(245,200,66,0.35),_transparent_55%)] pointer-events-none"></div>

    <div class="relative p-5 sm:p-6">
        <div class="flex flex-col lg:flex-row lg:items-start gap-6">
            {{-- Completion ring --}}
            <div class="flex items-center gap-5 shrink-0">
                <div class="relative size-28 sm:size-32">
                    <svg class="w-full h-full -rotate-90" viewBox="0 0 128 128">
                        <circle cx="64" cy="64" r="{{ $ringRadius }}" stroke-width="10" class="stroke-gray-200/80" fill="none"></circle>
                        <circle cx="64" cy="64" r="{{ $ringRadius }}" stroke-width="10"
                                class="{{ $percent >= 100 ? 'stroke-emerald-500' : ($meetsThreshold ? 'stroke-brand' : 'stroke-amber-500') }}"
                                fill="none" stroke-linecap="round"
                                stroke-dasharray="{{ format_number($ringCircumference, 2, '.', '') }}"
                                stroke-dashoffset="{{ format_number($ringDashoffset, 2, '.', '') }}"></circle>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="text-3xl font-bold text-gray-900 leading-none tabular-nums">{{ $percent }}%</span>
                        <span class="text-[10px] uppercase tracking-wide text-gray-500 mt-1">{{ __('borrower.profile.completion_hub_title') }}</span>
                    </div>
                </div>
                <div class="min-w-0 lg:hidden">
                    <h2 class="font-bold text-gray-900">{{ __('borrower.profile.completion_hub_title') }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.profile.completion_hub_subtitle') }}</p>
                </div>
            </div>

            <div class="flex-1 min-w-0">
                <div class="hidden lg:block">
                    <h2 class="font-bold text-gray-900">{{ __('borrower.profile.completion_hub_title') }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.profile.completion_hub_subtitle') }}</p>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs font-semibold mb-2">
                        <span class="text-gray-600">
                            {{ __('borrower.profile.completion_required_progress', ['done' => $requiredDone, 'total' => $requiredTotal]) }}
                        </span>
                        @unless ($meetsThreshold)
                            <span class="text-amber-700">{{ __('borrower.profile.completion_threshold_hint', ['percent' => $threshold]) }}</span>
                        @else
                            <span class="text-emerald-700">{{ __('borrower.profile.section_complete') }}</span>
                        @endunless
                    </div>
                    <div class="h-2 rounded-full bg-gray-200/80 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 {{ $percent >= 100 ? 'bg-emerald-500' : ($meetsThreshold ? 'bg-brand' : 'bg-amber-500') }}"
                             style="width: {{ $percent }}%"></div>
                    </div>
                </div>

                @if ($identityDeferred)
                    <div class="mt-4 rounded-xl bg-brand-muted/50 ring-1 ring-brand/15 px-4 py-3">
                        <p class="text-sm font-semibold text-brand">{{ __('borrower.profile.identity_deferred_title') }}</p>
                        <p class="text-xs text-gray-600 mt-1">{{ __('borrower.profile.identity_deferred_body') }}</p>
                    </div>
                @endif

                @if ($remainingSections !== [])
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($remainingSections as $section)
                            @php
                                $chipClass = match ($section['status'] ?? 'missing') {
                                    'stale'   => 'bg-amber-50 text-amber-900 ring-amber-200',
                                    'pending' => 'bg-sky-50 text-sky-900 ring-sky-200',
                                    default   => 'bg-red-50 text-red-900 ring-red-200',
                                };
                            @endphp
                            <a href="{{ $section['action_url'] ?? '#' }}"
                               class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition hover:opacity-90 {{ $chipClass }}">
                                <span class="size-1.5 rounded-full bg-current opacity-70"></span>
                                {{ __('borrower.profile.continue_section', ['section' => $section['label']]) }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Tab status grid --}}
        <div class="mt-6 pt-5 border-t border-gray-100/80">
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('borrower.profile.account_nav') }}</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach ($tabStatuses as $key => $tab)
                    @php
                        $complete = $tab['complete'] ?? false;
                        $required = $tab['required'] ?? false;
                        $cardClass = $complete
                            ? 'ring-emerald-300/80 bg-emerald-50/80'
                            : ($required ? 'ring-red-300/80 bg-red-50/60' : 'ring-gray-200/80 bg-white/70');
                        $dotClass = $complete ? 'bg-emerald-500' : ($required ? 'bg-red-500' : 'bg-gray-300');
                        $statusLabel = $complete
                            ? __('borrower.profile.tab_complete')
                            : ($required ? __('borrower.profile.tab_incomplete') : __('borrower.profile.tab_optional'));
                    @endphp
                    <a href="{{ $tab['url'] }}"
                       class="rounded-xl px-3 py-3 ring-1 transition hover:shadow-sm {{ $cardClass }}">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full shrink-0 {{ $dotClass }}"></span>
                            <span class="text-sm font-semibold text-gray-900 truncate">{{ $tab['label'] }}</span>
                        </div>
                        <p class="mt-1 text-[10px] uppercase tracking-wide font-semibold {{ $complete ? 'text-emerald-700' : ($required ? 'text-red-700' : 'text-gray-500') }}">
                            {{ $statusLabel }}
                        </p>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>
