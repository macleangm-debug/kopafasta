@props([
    'tabs' => [],
    'items' => [],
    'groups' => [],
    'active' => null,
    'expanded' => true,
])

@php
    $source = $items !== [] ? array_values($items) : array_values($tabs);
    $hasMissing = collect($source)->contains(fn ($row) => ($row['status'] ?? '') === 'missing' || blank($row['url'] ?? null));
    $useExpanded = $expanded && ! $hasMissing && collect($source)->contains(fn ($row) => filled($row['url'] ?? null));
    $presentTabs = array_values(array_filter($source, fn ($tab) => filled($tab['url'] ?? null)));
    $active = $active ?? ($presentTabs[0]['key'] ?? null);
    $groupList = array_values($groups);
    if ($groupList === []) {
        $used = collect($source)->pluck('category')->filter()->unique()->values()->all();
        $labels = [
            'identity' => 'Identity',
            'residence' => 'Residence',
            'financial' => 'Financial',
            'business' => 'Business',
            'collateral' => 'Collateral',
            'other' => 'Other',
        ];
        foreach ($used as $key) {
            $groupList[] = ['key' => $key, 'label' => $labels[$key] ?? ucfirst((string) $key)];
        }
    }
    $defaultGroup = $groupList[0]['key'] ?? null;
@endphp

@if ($source === [])
    {{ $slot }}
@elseif ($useExpanded)
    <div class="document-holder rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden"
         x-data="{
            doc: @js($active),
            tabs: @js(collect($presentTabs)->mapWithKeys(fn ($tab) => [$tab['key'] => $tab])->all()),
         }">
        <div class="px-4 sm:px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2">
            @foreach ($presentTabs as $tab)
                <button type="button"
                        @click="doc = @js($tab['key'])"
                        :class="doc === @js($tab['key']) ? 'bg-brand text-white ring-brand' : 'bg-gray-100 text-gray-700 ring-transparent hover:bg-gray-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold ring-1 transition">
                    {{ $tab['label'] }}
                </button>
            @endforeach
            <div class="ml-auto">
                @foreach ($presentTabs as $tab)
                    <div x-show="doc === @js($tab['key'])" x-cloak>
                        <x-admin.letter-actions :url="$tab['url']" :preview-label="$tab['preview_label'] ?? 'Open document'" :use-admin-preview="$tab['use_admin_preview'] ?? true" />
                    </div>
                @endforeach
            </div>
        </div>

        @foreach ($presentTabs as $tab)
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
@else
    <div class="document-holder space-y-3"
         @if ($groupList !== [])
             x-data="{ holder: @js($defaultGroup) }"
         @endif>
        @if ($groupList !== [])
            <div class="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
                @foreach ($groupList as $group)
                    @php
                        $gKey = $group['key'] ?? '';
                        $missingCount = collect($source)->where('category', $gKey)->where('status', 'missing')->count();
                    @endphp
                    <button type="button"
                            @click="holder = @js($gKey)"
                            :class="holder === @js($gKey) ? 'bg-brand text-white ring-brand' : 'bg-slate-50 text-slate-700 ring-slate-200'"
                            class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold ring-1">
                        {{ $group['label'] ?? $gKey }}
                        @if ($missingCount > 0)
                            <span class="opacity-80">!</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif

        @foreach ($source as $item)
            @php
                $url = $item['url'] ?? null;
                $kind = $item['kind'] ?? (is_string($url) && str_contains(strtolower($url), '.pdf') ? 'pdf' : 'image');
                $status = $item['status'] ?? (filled($url) ? 'present' : 'missing');
                $isMissing = $status === 'missing' || blank($url);
                $isPdf = $kind === 'pdf';
                $category = $item['category'] ?? null;
            @endphp
            <article
                @if ($groupList !== [] && $category)
                    x-show="holder === @js($category)" x-cloak
                @endif
                class="rounded-2xl overflow-hidden ring-1 {{ $isMissing ? 'ring-rose-200 bg-rose-50/40' : 'ring-brand/10 bg-white' }}">
                <div class="px-4 py-3 flex items-start gap-3">
                    @if ($url && ! $isPdf)
                        <x-admin.document-preview :url="$url" :label="$item['label'] ?? 'Document'" variant="thumbnail" type="image" class="h-20 w-20 sm:h-24 sm:w-24" />
                    @elseif ($url && $isPdf)
                        <x-admin.document-preview :url="$url" :label="$item['label'] ?? 'Document'" variant="thumbnail" type="pdf" class="h-20 w-20 sm:h-24 sm:w-24" />
                    @else
                        <div class="h-20 w-20 sm:h-24 sm:w-24 rounded-xl ring-1 ring-dashed ring-rose-300 bg-white grid place-items-center text-[10px] font-bold uppercase tracking-wide text-rose-700 shrink-0">
                            Missing
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] uppercase tracking-[0.2em] font-semibold {{ $isMissing ? 'text-rose-700' : 'text-brand' }}">
                            {{ $item['eyebrow'] ?? ($isMissing ? 'Missing' : 'Present') }}
                        </p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5 truncate">{{ $item['reference'] ?? $item['label'] ?? 'Document' }}</p>
                        @if (! empty($item['caption']))
                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $item['caption'] }}</p>
                        @endif
                        <p class="text-xs text-gray-500 mt-0.5">
                            @if (! empty($item['owner'])){{ $item['owner'] }}@endif
                            @if (! empty($item['uploaded_at'])){{ ! empty($item['owner']) ? ' · ' : '' }}{{ $item['uploaded_at'] }}@endif
                        </p>
                    </div>
                    @if ($url)
                        <x-admin.document-preview :url="$url" :label="$isPdf ? 'Open' : 'Preview'" :type="$kind" />
                    @endif
                </div>
                @if (! empty($item['history']))
                    <details class="px-4 pb-3 text-[11px] text-slate-500">
                        <summary class="cursor-pointer">{{ count($item['history']) }} earlier upload{{ count($item['history']) === 1 ? '' : 's' }}</summary>
                        <ul class="mt-1 space-y-1">
                            @foreach ($item['history'] as $older)
                                <li>
                                    @if (! empty($older['url']))
                                        <x-admin.document-preview :url="$older['url']" label="Previous" variant="link" :type="$older['kind'] ?? 'image'" />
                                    @endif
                                    @if (! empty($older['uploaded_at']))
                                        <span> · {{ $older['uploaded_at'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </article>
        @endforeach
    </div>
@endif
