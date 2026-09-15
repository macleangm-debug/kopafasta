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
        return $this->operationalRoles();
    }

    /**
     * Console Users list: internal staff + partner portal accounts.
     * Excludes borrower/customer member roles.
     *
     * @return list<string>
     */
    public function operationalRoles(): array
    {
        $memberRoles = ['borrower', 'customer'];
        $roles = [];

        foreach ($this->definitions() as $code => $definition) {
            if (in_array($code, $memberRoles, true)) {
                continue;
            }
            if (($definition['staff'] ?? false) === true) {
                $roles[] = $code;
                continue;
            }
            if (isset($definition['portal'])) {
                $roles[] = $code;
            }
        }

        return $roles;
    }

    /**
     * Form labels for user create/edit capabilities.
     * Agent is shown as Customer Support (role code remains agent).
     *
     * @return array<string, string>
     */
    public function userFormRoleLabels(): array
    {
        $labels = [];
        foreach ($this->userFormRoles() as $code) {
            $labels[$code] = $code === 'agent'
                ? 'Customer Support'
                : $this->label($code);
        }

        return $labels;
    }

    /**
     * Pick the home-desk primary from a multi-capability selection.
     *
     * @param  list<string>  $roleCodes
     */
    public function resolvePrimaryRole(array $roleCodes): string
    {
        $roleCodes = array_values(array_unique(array_filter($roleCodes, fn ($c) => is_string($c) && $c !== '')));
        if ($roleCodes === []) {
            return 'officer';
        }

        $priority = [
            'admin', 'super_admin', 'manager', 'credit_committee',
            'credit_analyst', 'officer', 'partner_support', 'asset_manager',
            'marketer', 'agent', 'auditor', 'collector',
        ];

        foreach ($priority as $code) {
            if (in_array($code, $roleCodes, true)) {
                return $code;
            }
        }

        return $roleCodes[0];
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
        $hasAccess = false;
        foreach ($user->roleCodes() as $code) {
            if ((bool) ($this->definition($code)['console_access'] ?? false)) {
                $hasAccess = true;
                break;
            }
        }

        return $hasAccess
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
        foreach ($user->roleCodes() as $code) {
            if ((bool) ($this->definition($code)['permission_bypass'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    public function hasPolicyBypass(User $user): bool
    {
        foreach ($user->roleCodes() as $code) {
            if ((bool) ($this->definition($code)['policy_bypass'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    public function isStaff(?string $role): bool
    {
        return (bool) ($this->definition($role)['staff'] ?? false);
    }

    public function isStaffUser(User $user): bool
    {
        foreach ($user->roleCodes() as $code) {
            if ($this->isStaff($code)) {
                return true;
            }
        }

        return false;
    }

    public function userHasAnyRole(User $user, array $allowedRoles): bool
    {
        return count(array_intersect($user->roleCodes(), $allowedRoles)) > 0;
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
