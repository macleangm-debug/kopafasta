<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class PermissionService
{
    /** @var array<string, list<string>> */
    private array $cache = [];

    public function has(User $user, string $permission): bool
    {
        if (app(RoleService::class)->hasPermissionBypass($user)) {
            return true;
        }

        return in_array($permission, $this->forUser($user), true);
    }

    public function hasAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->has($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function forUser(User $user): array
    {
        $key = (string) $user->id.'|'.$user->role;

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $role = Role::query()->where('code', $user->role)->first();
        $permissions = $role?->permissions;

        if (is_array($permissions) && count($permissions) > 0) {
            return $this->cache[$key] = array_values(array_unique($permissions));
        }

        $defaults = config('permissions.defaults.'.$user->role, []);

        return $this->cache[$key] = array_values(array_unique($defaults));
    }

    /** @return Collection<int, array{key: string, label: string, module: string}> */
    public function catalog(): Collection
    {
        return collect(config('permissions.permissions', []))
            ->map(fn (array $meta, string $key) => [
                'key'    => $key,
                'label'  => $meta['label'] ?? $key,
                'module' => $meta['module'] ?? 'general',
            ])
            ->values();
    }

    /** @return array<string, list<array{key: string, label: string}>> */
    public function catalogByModule(): array
    {
        $modules = config('permissions.modules', []);
        $grouped = [];

        foreach ($modules as $moduleKey => $moduleLabel) {
            $grouped[$moduleKey] = [
                'label'       => $moduleLabel,
                'permissions' => $this->catalog()
                    ->where('module', $moduleKey)
                    ->map(fn (array $item) => ['key' => $item['key'], 'label' => $item['label']])
                    ->values()
                    ->all(),
            ];
        }

        return $grouped;
    }
}
