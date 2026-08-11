@php
    $groups = config('settings_nav', []);
@endphp

<x-admin.layout title="Settings hub" heading="Settings hub" subheading="Configure the platform by area — one clear place for every setting">
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-6 text-white ring-1 ring-brand/20 shadow-sm flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ brand_name() }}</p>
            <p class="text-xl font-bold mt-1 tracking-tight">Configure once, reuse everywhere</p>
            <p class="text-sm text-white/80 mt-2 max-w-2xl">Lending, identity, finance, growth, partners, and communications — grouped so any team member can find the right control. On each settings page, use <span class="text-brand-gold font-semibold">Page guide</span> for what to set and what it affects.</p>
        </div>
        <x-admin.settings-help-drawer page="hub" />
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
