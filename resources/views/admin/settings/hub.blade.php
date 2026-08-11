@php
    $groups = config('settings_nav', []);
    $flat = [];
    foreach ($groups as $groupName => $links) {
        foreach ($links as $link) {
            $label = $link[0];
            $route = $link[1];
            $key = $link[2] ?? \Illuminate\Support\Str::slug($label);
            $keywords = strtolower((string) ($link[3] ?? ''));
            try {
                $url = route($route);
            } catch (\Throwable) {
                continue;
            }
            $flat[] = [
                'group' => $groupName,
                'key' => $key,
                'label' => $label,
                'url' => $url,
                'haystack' => strtolower($groupName.' '.$label.' '.$key.' '.$keywords),
            ];
        }
    }
@endphp

<x-admin.layout title="Settings hub" heading="Settings hub" subheading="Configure the platform by area — one clear place for every setting">
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-6 text-white ring-1 ring-brand/20 shadow-sm"
         x-data="{
            q: '',
            items: @js($flat),
            get matches() {
                const q = this.q.trim().toLowerCase();
                if (! q) return [];
                return this.items.filter(i => i.haystack.includes(q)).slice(0, 12);
            }
         }">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ brand_name() }}</p>
                <p class="text-xl font-bold mt-1 tracking-tight">Configure once, reuse everywhere</p>
                <p class="text-sm text-white/80 mt-2 max-w-2xl">Search here to jump to a settings page. On each page (and each sub-tab), use <span class="text-brand-gold font-semibold">Page guide</span> on the right for what to set and what it affects.</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <div class="relative">
                    <input type="search"
                           x-model="q"
                           placeholder="Find a setting…"
                           class="w-56 sm:w-72 rounded-xl border-0 bg-white/95 px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 outline-none ring-2 ring-white/30 focus:ring-brand-gold"
                           autocomplete="off">
                    <div x-show="q.trim().length > 0" x-cloak
                         @click.outside="q = ''"
                         class="absolute right-0 top-full z-20 mt-1 w-80 max-h-80 overflow-y-auto rounded-xl bg-white shadow-lg ring-1 ring-gray-200 text-gray-900">
                        <template x-if="matches.length === 0">
                            <p class="px-3 py-2 text-xs text-gray-500">No matches</p>
                        </template>
                        <template x-for="item in matches" :key="item.key + item.url">
                            <a :href="item.url"
                               class="block px-3 py-2.5 text-sm hover:bg-brand-muted/40 border-b border-gray-50 last:border-0">
                                <span class="font-semibold text-gray-900" x-text="item.label"></span>
                                <span class="block text-[11px] text-gray-500" x-text="item.group"></span>
                            </a>
                        </template>
                    </div>
                </div>
                <x-admin.settings-help-drawer page="hub" :on-dark="true" />
            </div>
        </div>
    </div>
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach ($groups as $groupName => $links)
            <section class="bg-white rounded-2xl ring-1 ring-brand/10 overflow-hidden shadow-sm hover:shadow-md hover:ring-brand/20 transition">
                <div class="bg-gradient-to-r from-brand-muted/60 to-white px-5 py-3 border-b border-brand/10">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-brand">{{ $groupName }}</h2>
                </div>
                <ul class="p-4 space-y-1">
                    @foreach ($links as $link)
                        @php
                            [$label, $route] = $link;
                        @endphp
                        <li>
                            <a href="{{ route($route) }}"
                               class="flex items-center justify-between gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-800 hover:bg-brand-muted/40 hover:text-brand transition">
                                <span>{{ $label }}</span>
                                <span class="text-brand/50" aria-hidden="true">→</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-admin.layout>
