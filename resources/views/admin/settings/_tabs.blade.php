@props(['active' => 'company'])
@php
    $navGroups = config('settings_nav', []);
    $groups = [];
    foreach ($navGroups as $groupName => $links) {
        $tabs = [];
        foreach ($links as $link) {
            $label = $link[0];
            $route = $link[1];
            $key = $link[2] ?? Str::slug($label);
            $tabs[$key] = [$label, $route];
        }
        $groups[$groupName] = $tabs;
    }

    $activeGroup = collect($groups)->search(fn ($tabs) => array_key_exists($active, $tabs)) ?: array_key_first($groups);
@endphp

<div class="mb-6 space-y-3" x-data="{ group: @js($activeGroup) }">
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
        <a href="{{ route('admin.settings.index') }}" class="text-xs font-semibold text-brand hover:underline">← Settings hub</a>
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
