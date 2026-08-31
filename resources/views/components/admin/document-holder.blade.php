@props([
    'tabs' => [],
    'active' => null,
])

@php
    $tabs = array_values(array_filter($tabs, fn ($tab) => filled($tab['url'] ?? null)));
    $active = $active ?? ($tabs[0]['key'] ?? null);
@endphp

@if ($tabs === [])
    {{ $slot }}
@else
    <div class="document-holder rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden"
         x-data="{
            doc: @js($active),
            tabs: @js(collect($tabs)->mapWithKeys(fn ($tab) => [$tab['key'] => $tab])->all()),
         }">
        <div class="px-4 sm:px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2">
            @foreach ($tabs as $tab)
                <button type="button"
                        @click="doc = @js($tab['key'])"
                        :class="doc === @js($tab['key']) ? 'bg-brand text-white ring-brand' : 'bg-gray-100 text-gray-700 ring-transparent hover:bg-gray-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold ring-1 transition">
                    {{ $tab['label'] }}
                </button>
            @endforeach
            <div class="ml-auto">
                @foreach ($tabs as $tab)
                    <div x-show="doc === @js($tab['key'])" x-cloak>
                        <x-admin.letter-actions :url="$tab['url']" :preview-label="$tab['preview_label'] ?? 'Open document'" :use-admin-preview="$tab['use_admin_preview'] ?? true" />
                    </div>
                @endforeach
            </div>
        </div>

        @foreach ($tabs as $tab)
            <div class="px-5 py-3 border-b border-gray-100" x-show="doc === @js($tab['key'])" x-cloak>
                <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">{{ $tab['eyebrow'] ?? $tab['label'] }}</p>
                <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $tab['reference'] ?? '' }}</p>
                @if (! empty($tab['caption']))
                    <p class="text-xs text-gray-500 mt-0.5">{{ $tab['caption'] }}</p>
                @endif
                @if (! empty($tab['owner']) || ! empty($tab['uploaded_at']) || ! empty($tab['request_label']))
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if (! empty($tab['owner'])) {{ $tab['owner'] }} @endif
                        @if (! empty($tab['uploaded_at'])) · Uploaded {{ $tab['uploaded_at'] }} @endif
                        @if (! empty($tab['request_label'])) · {{ $tab['request_label'] }} @endif
                    </p>
                @endif
            </div>
        @endforeach

        <div class="bg-[#cfd6d2] px-4 sm:px-10 py-8">
            <div class="mx-auto w-full max-w-[794px] bg-white shadow-[0_24px_48px_-16px_rgba(15,61,46,0.45)] ring-1 ring-black/10">
                <iframe :src="tabs[doc]?.url"
                        :title="tabs[doc]?.label || 'Document'"
                        class="w-full h-[80vh] min-h-[640px] border-0 bg-white"></iframe>
            </div>
            <p class="text-center text-[11px] text-[#5c6b64] mt-3">A4 preview - one document at a time</p>
        </div>
    </div>
@endif
