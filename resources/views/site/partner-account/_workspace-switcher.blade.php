@props([
    'partner' => null,
    'variant' => 'chrome', // chrome = compact header control; card = legacy full card (unused)
    'chromeTone' => 'light', // light | onDark
])

@php
    $partner = $partner ?? null;
    $workspaces = [];
    $current = null;
    $currentLabel = null;
    if ($partner instanceof \App\Models\Partner) {
        $ws = app(\App\Services\PartnerWorkspaceService::class);
        if ($ws->canSwitch($partner)) {
            $workspaces = $ws->workspaces($partner);
            $current = $ws->currentKey($partner);
            $currentLabel = collect($workspaces)->firstWhere('key', $current)['label']
                ?? __('site.partner_workspace.service');
        }
    }
@endphp

@if ($workspaces !== [])
    <div class="{{ $variant === 'card' ? 'rounded-2xl bg-white ring-1 ring-gray-200 p-5 sm:p-6 space-y-4' : 'relative' }}"
         x-data="{ workspaceOpen: false }">
        @if ($variant === 'card')
            <div>
                <h2 class="text-lg font-bold text-gray-900">{{ __('site.partner_workspace.title') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('site.partner_workspace.hint') }}</p>
            </div>
        @endif

        <button type="button"
                @click="workspaceOpen = true"
                @class([
                    'inline-flex items-center gap-1.5 rounded-xl text-sm font-semibold transition',
                    'w-full sm:w-auto justify-between gap-3 ring-1 ring-brand/20 bg-brand-muted/40 hover:bg-brand-muted px-4 py-3 text-brand' => $variant === 'card',
                    'max-w-[14rem] sm:max-w-xs truncate bg-white/10 hover:bg-white/15 text-white ring-1 ring-white/20 px-2.5 py-1.5' => $variant === 'chrome' && $chromeTone === 'onDark',
                    'max-w-[14rem] sm:max-w-xs truncate bg-brand-muted/50 hover:bg-brand-muted text-brand ring-1 ring-brand/15 px-2.5 py-1.5' => $variant === 'chrome' && $chromeTone !== 'onDark',
                ])
                title="{{ __('site.partner_workspace.switch') }}">
            <span class="truncate">{{ $partner->name ?: $currentLabel }}</span>
            <span class="hidden sm:inline shrink-0 text-[11px] font-medium opacity-80">· {{ $currentLabel }}</span>
            <svg class="w-4 h-4 shrink-0 opacity-80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <x-site.action-panel :title="__('site.partner_workspace.switch')" open="workspaceOpen" size="md">
            <p class="text-xs text-gray-500 mb-3">{{ __('site.partner_workspace.same_identity') }}</p>
            <ul class="space-y-1">
                @foreach ($workspaces as $row)
                    <li>
                        <form method="POST" action="{{ route('site.partner.workspace.switch') }}">
                            @csrf
                            <input type="hidden" name="workspace" value="{{ $row['key'] }}">
                            <button type="submit"
                                    class="w-full text-left rounded-xl px-4 py-3 text-sm font-semibold
                                           {{ $current === $row['key'] ? 'bg-brand text-white' : 'hover:bg-gray-50 text-gray-900 ring-1 ring-gray-200' }}">
                                {{ $row['label'] }}
                                @if ($current === $row['key'])
                                    <span class="ml-1">✓</span>
                                @endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-site.action-panel>
    </div>
@endif
