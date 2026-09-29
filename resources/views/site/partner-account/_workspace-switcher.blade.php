@props([
    'partner' => null,
])

@php
    $partner = $partner ?? null;
    $workspaces = [];
    $current = null;
    if ($partner instanceof \App\Models\Partner) {
        $ws = app(\App\Services\PartnerWorkspaceService::class);
        if ($ws->canSwitch($partner)) {
            $workspaces = $ws->workspaces($partner);
            $current = $ws->currentKey($partner);
        }
    }
@endphp

@if ($workspaces !== [])
    <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 sm:p-6 space-y-4"
         x-data="{ workspaceOpen: false }">
        <div>
            <h2 class="text-lg font-bold text-gray-900">{{ __('site.partner_workspace.title') }}</h2>
            <p class="text-sm text-gray-600 mt-1">{{ __('site.partner_workspace.hint') }}</p>
        </div>

        <button type="button"
                @click="workspaceOpen = true"
                class="w-full sm:w-auto inline-flex items-center justify-between gap-3 rounded-xl ring-1 ring-brand/20 bg-brand-muted/40 hover:bg-brand-muted px-4 py-3 text-sm font-semibold text-brand">
            <span>{{ __('site.partner_workspace.switch') }}</span>
            <span class="text-brand/80 font-medium">
                {{ collect($workspaces)->firstWhere('key', $current)['label'] ?? __('site.partner_workspace.service') }}
            </span>
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
