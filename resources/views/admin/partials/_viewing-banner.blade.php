@php
    $roleViewCtx = app(\App\Services\AdminRoleViewService::class);
    $roleViewBanner = $roleViewCtx->bannerLabel();
@endphp
@if ($roleViewBanner)
    <div class="bg-amber-50 border-b border-amber-200 text-amber-950">
        <div class="flex items-center justify-between gap-3 px-4 lg:px-6 py-2 text-sm">
            <p class="font-semibold truncate">
                {{ __('admin.role_view.viewing') }}: {{ $roleViewBanner }}
            </p>
            <form method="POST" action="{{ route('admin.role-view.exit') }}" class="shrink-0">
                @csrf
                <button type="submit"
                        class="inline-flex items-center rounded-lg bg-white ring-1 ring-amber-300 px-3 py-1 text-xs font-bold text-amber-900 hover:bg-amber-100">
                    {{ __('admin.role_view.exit') }}
                </button>
            </form>
        </div>
    </div>
@endif
