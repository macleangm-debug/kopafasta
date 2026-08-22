@props([
    'action',
    'method' => 'PUT',
    'submitLabel' => 'Save settings',
    'enctype' => null,
    'tabs' => [],
    'defaultTab' => null,
    'formClass' => 'space-y-6',
])

@php
    /** @var array<string, string> $tabs */
    $tabKeys = array_keys($tabs);
    $requestedTab = (string) old('_tab', request('tab', ''));
    $defaultTab = $defaultTab ?? ($tabKeys[0] ?? 'main');
    if ($tabKeys !== [] && ! in_array($requestedTab, $tabKeys, true)) {
        $requestedTab = $defaultTab;
    }
    $startEditing = $errors->any() || request()->boolean('edit');
    $formId = $attributes->get('id') ?: 'settings-editor-'.substr(md5($action), 0, 8);
@endphp

<div
    {{ $attributes->except('id')->merge(['class' => 'space-y-4']) }}
    x-data="{
        editing: @js($startEditing),
        tab: @js($tabKeys === [] ? 'main' : $requestedTab),
        setTab(next) {
            this.tab = next;
            const url = new URL(window.location.href);
            if (next && next !== 'main') {
                url.searchParams.set('tab', next);
            } else {
                url.searchParams.delete('tab');
            }
            history.replaceState({}, '', url);
        },
        cancelEdit() {
            window.location.assign(window.location.pathname + window.location.search);
        },
    }"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        @if ($tabKeys !== [])
            <div class="flex flex-wrap gap-2">
                @foreach ($tabs as $key => $label)
                    <button type="button" @click="setTab(@js($key))"
                            :class="tab === @js($key) ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-600 ring-gray-200 hover:bg-brand-muted/40'"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold ring-1 transition">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @else
            <p class="text-xs text-gray-500" x-show="!editing" x-cloak>Read-only. Click Edit to change these settings.</p>
        @endif

        <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
            <button type="button" x-show="!editing" @click="editing = true"
                    class="inline-flex items-center rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-light">
                Edit
            </button>
            <button type="button" x-show="editing" x-cloak @click="cancelEdit()"
                    class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50">
                Cancel
            </button>
            <button type="submit" form="{{ $formId }}" x-show="editing" x-cloak
                    class="inline-flex items-center rounded-xl bg-brand-gold px-4 py-2 text-xs font-bold text-brand hover:brightness-95">
                {{ $submitLabel }}
            </button>
        </div>
    </div>

    @if ($tabKeys !== [])
        <p class="text-xs text-gray-500" x-show="!editing" x-cloak>Read-only until you click Edit. All tabs save together.</p>
    @endif

    <form
        id="{{ $formId }}"
        method="POST"
        action="{{ $action }}"
        @if ($enctype) enctype="{{ $enctype }}" @endif
        class="{{ $formClass }}"
        novalidate
        @submit="if ($refs.activeTab) { $refs.activeTab.value = tab }"
    >
        @csrf
        @method($method)
        @if ($tabKeys !== [])
            <input type="hidden" name="_tab" x-ref="activeTab" value="{{ $requestedTab }}">
        @endif
        <fieldset :disabled="!editing" class="min-w-0 space-y-6 disabled:opacity-90">
            {{ $slot }}
        </fieldset>
    </form>
</div>
