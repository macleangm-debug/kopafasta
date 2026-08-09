@props(['active' => 'company'])
@php
    $navGroups = config('settings_nav', []);
    $groups = [];
    $flat = [];
    foreach ($navGroups as $groupName => $links) {
        $tabs = [];
        foreach ($links as $link) {
            $label = $link[0];
            $route = $link[1];
            $key = $link[2] ?? Str::slug($label);
            $keywords = strtolower((string) ($link[3] ?? ''));
            try {
                $url = route($route);
            } catch (\Throwable) {
                continue;
            }
            $tabs[$key] = [$label, $route];
            $flat[] = [
                'group' => $groupName,
                'key' => $key,
                'label' => $label,
                'url' => $url,
                'haystack' => strtolower($groupName.' '.$label.' '.$key.' '.$keywords),
            ];
        }
        $groups[$groupName] = $tabs;
    }

    $activeGroup = collect($groups)->search(fn ($tabs) => array_key_exists($active, $tabs)) ?: array_key_first($groups);
@endphp

<div class="mb-6 space-y-3"
     x-data="{
        group: @js($activeGroup),
        q: '',
        items: @js($flat),
        get matches() {
            const q = this.q.trim().toLowerCase();
            if (! q) return [];
            return this.items.filter(i => i.haystack.includes(q)).slice(0, 12);
        }
     }">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand/10 pb-3">
        <div class="flex flex-wrap gap-2">
            @foreach (array_keys($groups) as $groupName)
                <button type="button"
                        @click="group = @js($groupName); q = ''"
                        :class="group === @js($groupName) && !q ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-600 ring-1 ring-brand/15 hover:bg-brand-muted/40'"
                        class="px-3 py-1.5 rounded-xl text-sm font-semibold transition ring-1">
                    {{ $groupName }}
                </button>
            @endforeach
        </div>
        <div class="flex items-center gap-3">
            <div class="relative">
                <input type="search"
                       x-model="q"
                       placeholder="Find a setting…"
                       class="w-52 sm:w-64 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/10"
                       autocomplete="off">
                <div x-show="q.trim().length > 0" x-cloak
                     @click.outside="q = ''"
                     class="absolute right-0 top-full z-20 mt-1 w-72 max-h-72 overflow-y-auto rounded-xl bg-white shadow-lg ring-1 ring-gray-200">
                    <template x-if="matches.length === 0">
                        <p class="px-3 py-2 text-xs text-gray-500">No matches</p>
                    </template>
                    <template x-for="item in matches" :key="item.key + item.url">
                        <a :href="item.url"
                           class="block px-3 py-2 text-sm hover:bg-brand-muted/40 border-b border-gray-50 last:border-0">
                            <span class="font-semibold text-gray-900" x-text="item.label"></span>
                            <span class="block text-[11px] text-gray-500" x-text="item.group"></span>
                        </a>
                    </template>
                </div>
            </div>
            <a href="{{ route('admin.settings.index') }}" class="text-xs font-semibold text-brand hover:underline">← Settings hub</a>
        </div>
    </div>

    @foreach ($groups as $groupName => $tabs)
        <div x-show="!q && group === @js($groupName)" x-cloak class="flex flex-wrap gap-2">
            @foreach ($tabs as $key => [$label, $route])
                <a href="{{ route($route) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-semibold transition ring-1 {{ $active === $key ? 'bg-brand-muted text-brand ring-brand/25' : 'bg-white text-gray-600 ring-gray-200 hover:ring-brand/20' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    @endforeach
</div>
