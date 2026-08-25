@props(['banner' => []])

@if ($banner['show'] ?? false)
    <div class="mb-6 rounded-2xl kf-premium-panel p-6 sm:p-8 relative">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="min-w-0 flex-1">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.onboarding.eyebrow') }}</p>
                <h2 class="text-xl sm:text-2xl font-bold mt-1">
                    {{ $banner['title'] ?? __('borrower.onboarding.title_complete') }}
                    — {{ __('borrower.onboarding.percent_complete', ['percent' => $banner['percent'] ?? 0]) }}
                </h2>
                <div class="mt-4 h-2 rounded-full bg-white/20 overflow-hidden max-w-md">
                    <div class="h-full bg-brand-gold transition-all" style="width: {{ $banner['percent'] ?? 0 }}%"></div>
                </div>
                <ul class="mt-5 space-y-2.5">
                    @foreach (($banner['items'] ?? []) as $item)
                        @php
                            $status = $item['status'] ?? 'missing';
                            $icon = match ($status) {
                                'complete' => '✓',
                                'pending'  => '⏳',
                                'stale', 'refresh_required' => '↻',
                                default    => '⏳',
                            };
                            $suffix = match ($status) {
                                'complete' => '',
                                'pending'  => ' '.__('borrower.onboarding.status_pending'),
                                'stale', 'refresh_required' => ' '.__('borrower.onboarding.status_update_requested'),
                                default    => ' '.__('borrower.onboarding.status_missing'),
                            };
                        @endphp
                        <li class="flex items-center gap-3 text-sm">
                            <span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold shrink-0
                                {{ $status === 'complete' ? 'bg-brand-gold text-brand' : 'bg-white/15 text-white' }}">
                                {{ $icon }}
                            </span>
                            <span class="{{ $status === 'complete' ? 'text-white/70 line-through' : 'text-white' }}">
                                {{ ucfirst($item['label'] ?? '') }}{{ $suffix }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if (! empty($banner['cta_url']))
                <a href="{{ $banner['cta_url'] }}"
                   class="relative shrink-0 self-start inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm">
                    {{ __('borrower.onboarding.cta') }}
                </a>
            @endif
        </div>
    </div>
@endif
