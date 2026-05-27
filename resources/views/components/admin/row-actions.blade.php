@props([
    'view' => null,
    'edit' => null,
    'destroy' => null,
    'confirm' => 'Delete this record? This cannot be undone.',
])

<div class="inline-flex items-center gap-1">
    @if ($view)
        <a href="{{ $view }}" title="View"
           class="inline-flex items-center justify-center size-8 rounded-lg text-gray-500 hover:text-amber-600 hover:bg-amber-50 transition">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        </a>
    @endif
    @if ($edit)
        <a href="{{ $edit }}" title="Edit"
           class="inline-flex items-center justify-center size-8 rounded-lg text-gray-500 hover:text-amber-600 hover:bg-amber-50 transition">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
        </a>
    @endif
    @if ($destroy)
        <form method="POST" action="{{ $destroy }}" class="inline"
              onsubmit="return confirm('{{ $confirm }}');">
            @csrf
            @method('DELETE')
            <button type="submit" title="Delete"
                    class="inline-flex items-center justify-center size-8 rounded-lg text-gray-500 hover:text-red-600 hover:bg-red-50 transition">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                </svg>
            </button>
        </form>
    @endif
</div>
