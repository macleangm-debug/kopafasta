@php
    $roleViewCtx = app(\App\Services\AdminRoleViewService::class);
    $roleViewBanner = $roleViewCtx->bannerLabel();
    $adminActor = auth('admin')->user();
    $exitLabel = ($adminActor && app(\App\Services\RoleService::class)->hasPermissionBypass($adminActor))
        ? __('admin.role_view.back_to_admin')
        : __('admin.role_view.exit');
@endphp
@if ($roleViewBanner)
    <div class="bg-amber-50 border-b border-amber-200 text-amber-950">
        <div class="flex items-center justify-between gap-3 px-4 lg:px-6 py-2.5 text-sm">
            <p class="font-semibold truncate min-w-0">
                {{ __('admin.role_view.viewing') }}: {{ $roleViewBanner }}
            </p>
            <form method="POST" action="{{ route('admin.role-view.exit') }}" class="shrink-0">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand text-white ring-1 ring-brand/40 px-3.5 py-1.5 text-xs font-bold hover:brightness-95 shadow-sm">
                    ← {{ $exitLabel }}
                </button>
            </form>
        </div>
    </div>
@endif
