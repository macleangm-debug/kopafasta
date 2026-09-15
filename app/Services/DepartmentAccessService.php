<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;

class DepartmentAccessService
{
    public function __construct(
        private readonly RoleService $roles,
    ) {}

    /** @return list<string> */
    public function allowedRoutePrefixes(User $user): array
    {
        if ($this->roles->userHasAnyRole($user, ['admin', 'super_admin'])) {
            return ['*'];
        }

        $codes = $user->relationLoaded('departments')
            ? $user->departments->pluck('code')->filter()->values()->all()
            : $user->departments()->pluck('code')->filter()->values()->all();

        if ($codes === []) {
            $primary = $user->department_id
                ? Department::query()->find($user->department_id)
                : null;
            if ($primary?->code) {
                $codes = [$primary->code];
            }
        }

        // Capability desks unlock modules even when the department pivot is stale
        // (e.g. agent on roles JSON while primary desk is Underwriting).
        foreach ($user->roleCodes() as $roleCode) {
            $desk = $this->roles->deskCode($roleCode);
            if ($desk) {
                $codes[] = $desk;
            }
        }

        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code) => strtoupper((string) $code),
            $codes,
        ))));

        if ($codes === []) {
            return ['*'];
        }

        $prefixes = [];
        foreach ($codes as $code) {
            $modules = config('departments.modules.'.$code, ['*']);
            if (in_array('*', $modules, true)) {
                return ['*'];
            }
            $prefixes = array_merge($prefixes, $modules);
        }

        return array_values(array_unique($prefixes));
    }

    public function canAccessRoute(User $user, string $routeName): bool
    {
        if ($this->isAlwaysAllowed($routeName)) {
            return true;
        }

        $prefixes = $this->allowedRoutePrefixes($user);

        if (in_array('*', $prefixes, true)) {
            return true;
        }

        foreach ($prefixes as $prefix) {
            if ($routeName === 'admin.'.$prefix || str_starts_with($routeName, 'admin.'.$prefix.'.')) {
                return true;
            }
        }

        return false;
    }

    private function isAlwaysAllowed(string $routeName): bool
    {
        return $routeName === 'admin.dashboard'
            || $routeName === 'admin.search'
            || str_starts_with($routeName, 'admin.nav.shortcuts')
            || str_starts_with($routeName, 'admin.settings.account-security');
    }
}
