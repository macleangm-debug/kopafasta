<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class RoleService
{
    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        return config('roles.definitions', []);
    }

    public function definition(?string $role): ?array
    {
        if ($role === null || $role === '') {
            return null;
        }

        return $this->definitions()[$role] ?? null;
    }

    public function label(?string $role): string
    {
        if ($role === null || $role === '') {
            return '';
        }

        static $labels = [];

        if (! array_key_exists($role, $labels)) {
            $fromDb = Role::query()->where('code', $role)->value('name');
            $labels[$role] = $fromDb
                ?: (string) ($this->definition($role)['label'] ?? ucfirst(str_replace('_', ' ', $role)));
        }

        return $labels[$role];
    }

    public function duty(?string $role): string
    {
        return (string) ($this->definition($role)['duty'] ?? '');
    }

    public function deskCode(?string $role): ?string
    {
        $desk = $this->definition($role)['desk'] ?? null;

        return is_string($desk) && $desk !== '' ? $desk : null;
    }

    /** @return list<string> */
    public function consoleRoles(): array
    {
        return $this->rolesWhere('console_access', true);
    }

    /** @return list<string> */
    public function staffRoles(): array
    {
        return $this->rolesWhere('staff', true);
    }

    /** @return list<string> */
    public function userFormRoles(): array
    {
        return $this->rolesWhere('user_form', true);
    }

    /** @return list<string> */
    public function usersFilterRoles(): array
    {
        return array_keys($this->definitions());
    }

    /** @return list<string> */
    public function portalRoles(): array
    {
        return array_keys(array_filter(
            $this->definitions(),
            fn (array $definition) => isset($definition['portal']),
        ));
    }

    /** @return list<string> */
    public function rolesForApiCapability(string $capability): array
    {
        return array_values(array_unique(config('roles.api_capabilities.'.$capability, [])));
    }

    /**
     * Resolve middleware tokens — either role codes or API capability names.
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    public function resolveApiRoles(array $tokens): array
    {
        $capabilities = config('roles.api_capabilities', []);
        $roles = [];

        foreach ($tokens as $token) {
            if (isset($capabilities[$token])) {
                $roles = array_merge($roles, $capabilities[$token]);
                continue;
            }

            $roles[] = $token;
        }

        return array_values(array_unique($roles));
    }

    public function hasConsoleAccess(User $user): bool
    {
        $definition = $this->definition($user->role);

        return (bool) ($definition['console_access'] ?? false)
            && (bool) ($user->is_active ?? true)
            && ! ($user->locked_until && $user->locked_until->isFuture());
    }

    /**
     * Default landing route after console login (intended URL still wins).
     */
    public function homeRoute(?User $user): string
    {
        return 'admin.dashboard';
    }

    public function hasPermissionBypass(User $user): bool
    {
        return (bool) ($this->definition($user->role)['permission_bypass'] ?? false);
    }

    public function hasPolicyBypass(User $user): bool
    {
        return (bool) ($this->definition($user->role)['policy_bypass'] ?? false);
    }

    public function isStaff(?string $role): bool
    {
        return (bool) ($this->definition($role)['staff'] ?? false);
    }

    public function isPortalRole(?string $role): bool
    {
        return isset($this->definition($role)['portal']);
    }

    /** @return list<string> */
    public function branchScopedStaffRoles(): array
    {
        return ['manager', 'officer', 'collector', 'credit_analyst', 'super_admin', 'partner_support'];
    }

    /** @return list<string> */
    private function rolesWhere(string $key, mixed $value): array
    {
        $roles = [];

        foreach ($this->definitions() as $code => $definition) {
            if (($definition[$key] ?? null) === $value) {
                $roles[] = $code;
            }
        }

        return $roles;
    }
}
