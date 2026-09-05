@props(['active' => 'company', 'helpPage' => null, 'showHelp' => true])
@php
    $navGroups = config('settings_nav', []);
    $groups = [];
    foreach ($navGroups as $groupName => $links) {
        $tabs = [];
        foreach ($links as $link) {
            $label = $link[0];
            $route = $link[1];
            $key = $link[2] ?? Str::slug($label);
            try {
                route($route);
            } catch (\Throwable) {
                continue;
            }
            $tabs[$key] = [$label, $route];
        }
        $groups[$groupName] = $tabs;
    }

    $activeGroup = collect($groups)->search(fn ($tabs) => array_key_exists($active, $tabs)) ?: array_key_first($groups);
    $helpPage = $helpPage ?? $active;
@endphp

<div class="mb-6 space-y-3"
     x-data="{ group: @js($activeGroup) }">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand/10 pb-3">
        <div class="flex flex-wrap gap-2">
            @foreach (array_keys($groups) as $groupName)
                <button type="button"
                        @click="group = @js($groupName)"
                        :class="group === @js($groupName) ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-600 ring-1 ring-brand/15 hover:bg-brand-muted/40'"
                        class="px-3 py-1.5 rounded-xl text-sm font-semibold transition ring-1">
                    {{ $groupName }}
                </button>
            @endforeach
        </div>
        <div class="flex items-center gap-3 ml-auto">
            @if (app()->environment('staging') || (bool) \App\Models\Setting::get('staging_payments.enabled', false))
                <span class="inline-flex items-center rounded-lg bg-amber-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-900 ring-1 ring-amber-300">
                    Staging · Test pricing
                </span>
            @elseif (app()->environment('production'))
                <span class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-800 ring-1 ring-emerald-200">
                    Production commercial tariff
                </span>
            @endif
            <a href="{{ route('admin.settings.index') }}" class="text-xs font-semibold text-brand hover:underline whitespace-nowrap">← Settings hub</a>
            @if ($showHelp)
                <x-admin.settings-help-drawer :page="$helpPage" />
            @endif
        </div>
    </div>

    @foreach ($groups as $groupName => $tabs)
        <div x-show="group === @js($groupName)" x-cloak class="flex flex-wrap gap-2">
            @foreach ($tabs as $key => [$label, $route])
                <a href="{{ route($route) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-semibold transition ring-1 {{ $active === $key ? 'bg-brand-muted text-brand ring-brand/25' : 'bg-white text-gray-600 ring-gray-200 hover:ring-brand/20' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    @endforeach
</div>
