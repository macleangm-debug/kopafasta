<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;

class DepartmentAccessService
{
    /** @return list<string> */
    public function allowedRoutePrefixes(User $user): array
    {
        if (in_array($user->role, ['admin', 'super_admin'], true)) {
            return ['*'];
        }

        $department = $user->department_id
            ? Department::query()->find($user->department_id)
            : null;

        if (! $department?->code) {
            return ['*'];
        }

        return config('departments.modules.'.$department->code, ['*']);
    }

    public function canAccessRoute(User $user, string $routeName): bool
    {
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
}
