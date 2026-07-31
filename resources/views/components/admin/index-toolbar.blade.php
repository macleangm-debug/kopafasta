@props([
    'route',          // route name prefix, e.g. 'admin.customers'
    'label' => 'New record',
    'message' => null,
    'showCreate' => true,
])

@if (session('status'))
    <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
        {{ session('status') }}
    </div>
@endif

@if ($showCreate)
    <div class="flex items-center justify-end mb-4">
        <a href="{{ route($route . '.create') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl shadow-sm transition">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            {{ $label }}
        </a>
    </div>
@endif
