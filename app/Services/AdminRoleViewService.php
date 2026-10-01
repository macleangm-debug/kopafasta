<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Admin User/Role switcher foundation.
 *
 * View profile → existing 360. Enter workspace → temporary viewing context.
 * Admin stays on the admin guard; audit actor remains the Admin.
 * Never changes PIN/password. Never merges Borrower with Partner.
 */
class AdminRoleViewService
{
    public const SESSION_KEY = 'admin_role_view';

    public const WORKSPACE_SUPPORT = 'support';

    /** Capability codes that share the Support workspace (historical roles preserved). */
    public const SUPPORT_ROLE_KEYS = ['agent', 'partner_support'];

    public function __construct(
        private PartnerWorkspaceService $partnerWorkspaces,
        private RoleService $roles,
        private AuditService $audit,
    ) {
    }

    public function active(): ?array
    {
        $ctx = Session::get(self::SESSION_KEY);
        if (! is_array($ctx) || empty($ctx['admin_id']) || empty($ctx['role_key'])) {
            return null;
        }

        $admin = Auth::guard('admin')->user();
        if (! $admin || (int) $ctx['admin_id'] !== (int) $admin->id) {
            return null;
        }

        // Legacy person-first sessions still valid while active.
        if (empty($ctx['subject_type'])) {
            $ctx['subject_type'] = ! empty($ctx['subject_id']) ? 'staff' : 'workspace';
        }

        return $ctx;
    }

    public function isActive(): bool
    {
        return $this->active() !== null;
    }

    public function bannerLabel(): ?string
    {
        $ctx = $this->active();
        if (! $ctx) {
            return null;
        }

        $role = (string) ($ctx['role_label'] ?? $ctx['role_key'] ?? '');
        $filterMode = (string) ($ctx['filter_mode'] ?? 'all');
        $name = (string) ($ctx['subject_name'] ?? '');

        if ($filterMode === 'staff' && $name !== '') {
            return trim($name.($role !== '' ? ' · '.$role : ''));
        }

        // Role-first: banner is the workspace name (team/aggregate view).
        return $role !== '' ? $role : 'Workspace';
    }

    /**
     * Internal staff workspace directory for Account / Role (role-first).
     * Every configured staff capability appears; agent + partner_support collapse to Support.
     *
     * @return list<array{
     *   key: string,
     *   label: string,
     *   underlying_roles: list<string>,
     *   staff_count: int,
     *   staff: list<array{id: int, name: string, subtitle: string, profile_url: string}>
     * }>
     */
    public function staffRoleDirectory(): array
    {
        $roleCodes = $this->roles->staffRoles();
        $byRole = array_fill_keys($roleCodes, []);

        $staffUsers = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $this->roles->isStaffUser($user));

        foreach ($staffUsers as $user) {
            foreach ($user->roleCodes() as $code) {
                if (! array_key_exists($code, $byRole)) {
                    continue;
                }
                if (! ($this->roles->definition($code)['staff'] ?? false)) {
                    continue;
                }
                $byRole[$code][$user->id] = [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'subtitle' => $this->staffSubtitle($user),
                    'profile_url' => route('admin.users.show', $user),
                ];
            }
        }

        $rows = [];
        $supportEmitted = false;
        foreach ($roleCodes as $code) {
            if (in_array($code, self::SUPPORT_ROLE_KEYS, true)) {
                if ($supportEmitted) {
                    continue;
                }
                $supportEmitted = true;
                $staff = [];
                foreach (self::SUPPORT_ROLE_KEYS as $supportCode) {
                    foreach ($byRole[$supportCode] ?? [] as $id => $row) {
                        $staff[$id] = $row;
                    }
                }
                $staff = array_values($staff);
                $rows[] = [
                    'key' => self::WORKSPACE_SUPPORT,
                    'label' => 'Support',
                    'underlying_roles' => self::SUPPORT_ROLE_KEYS,
                    'staff_count' => count($staff),
                    'staff' => $staff,
                ];
                continue;
            }

            $staff = array_values($byRole[$code]);
            $rows[] = [
                'key' => $code,
                'label' => $this->workspaceLabel($code),
                'underlying_roles' => [$code],
                'staff_count' => count($staff),
                'staff' => $staff,
            ];
        }

        return $rows;
    }

    /** @deprecated use workspaceLabel */
    public function staffRoleLabel(string $roleKey): string
    {
        return $this->workspaceLabel($roleKey);
    }

    public function workspaceLabel(string $workspaceKey): string
    {
        if (in_array($workspaceKey, [self::WORKSPACE_SUPPORT, 'agent', 'partner_support'], true)) {
            return 'Support';
        }

        return $this->roles->label($workspaceKey);
    }

    /**
     * @return list<string>
     */
    public function underlyingRolesForWorkspace(string $workspaceKey): array
    {
        if (in_array($workspaceKey, [self::WORKSPACE_SUPPORT, 'agent', 'partner_support'], true)) {
            return self::SUPPORT_ROLE_KEYS;
        }

        return [$workspaceKey];
    }

    public function isConfiguredStaffWorkspace(string $workspaceKey): bool
    {
        if ($workspaceKey === self::WORKSPACE_SUPPORT) {
            return true;
        }

        return in_array($workspaceKey, $this->roles->staffRoles(), true);
    }

    /**
     * Role-first enter: opens the role workspace without requiring a staff person.
     *
     * @return array{url: string}
     */
    public function enterWorkspace(User $admin, string $workspaceKey): array
    {
        if (! $admin->canAccessConsole()) {
            abort(403);
        }

        if (! $this->isConfiguredStaffWorkspace($workspaceKey)) {
            abort(422, 'Unknown staff workspace.');
        }

        $canonical = in_array($workspaceKey, self::SUPPORT_ROLE_KEYS, true)
            ? self::WORKSPACE_SUPPORT
            : $workspaceKey;

        $label = $this->workspaceLabel($canonical);
        $underlying = $this->underlyingRolesForWorkspace($canonical);

        // Overwrite viewing context in place (do not Session::forget first — that
        // briefly emptied active() mid-request and left Admin chrome on Support landings).
        Session::put(self::SESSION_KEY, [
            'admin_id' => $admin->id,
            'subject_type' => 'workspace',
            'subject_id' => null,
            'subject_name' => null,
            'filter_mode' => 'all',
            'workspace_key' => $canonical,
            'role_key' => $canonical,
            'role_label' => $label,
            'underlying_roles' => $underlying,
            'entered_at' => now()->toIso8601String(),
        ]);

        $support = app(\App\Services\Support\CustomerSupportWorkspaceService::class);
        if (in_array($canonical, [self::WORKSPACE_SUPPORT, ...self::SUPPORT_ROLE_KEYS], true)) {
            $support->markSupportShell();
        } else {
            // Switching Role A → Role B must not leave Support sticky chrome.
            $support->clearSupportShell();
        }

        $this->audit->logAdminAction($admin, 'admin.role_view.enter', null, [
            'subject_type' => 'workspace',
            'role_key' => $canonical,
            'filter_mode' => 'all',
            'mode' => 'viewing',
        ]);

        return ['url' => $this->workspaceHomeUrl($canonical)];
    }

    /**
     * Drop viewing/partner/Support shell only — Admin identity and guard stay.
     * Used by Exit / Admin return — not mid-enter (enter overwrites SESSION_KEY).
     */
    public function clearViewingContextPreservingAdmin(?User $admin = null): void
    {
        $ctx = Session::get(self::SESSION_KEY);
        $this->clearWebPartnerSessionIfViewing(is_array($ctx) ? $ctx : null);
        Session::forget(self::SESSION_KEY);
        Session::forget(PartnerWorkspaceService::SESSION_KEY);
        app(\App\Services\Support\CustomerSupportWorkspaceService::class)->clearSupportShell();
    }

    /**
     * Inside a role workspace: All / team view or a specific assigned staff member.
     *
     * @return array{url: string}
     */
    public function selectWorkspaceStaff(User $admin, ?int $staffId): array
    {
        $ctx = $this->active();
        if (! $ctx || (int) ($ctx['admin_id'] ?? 0) !== (int) $admin->id) {
            abort(422, 'No active role workspace.');
        }

        $workspaceKey = (string) ($ctx['workspace_key'] ?? $ctx['role_key'] ?? '');
        $underlying = $this->underlyingRolesForWorkspace($workspaceKey);

        if ($staffId === null || $staffId <= 0) {
            $ctx['subject_type'] = 'workspace';
            $ctx['subject_id'] = null;
            $ctx['subject_name'] = null;
            $ctx['filter_mode'] = 'all';
            Session::put(self::SESSION_KEY, $ctx);

            $this->audit->logAdminAction($admin, 'admin.role_view.filter', null, [
                'role_key' => $workspaceKey,
                'filter_mode' => 'all',
            ]);

            return ['url' => $this->workspaceHomeUrl($workspaceKey)];
        }

        $staff = User::query()->findOrFail($staffId);
        $codes = $staff->roleCodes();
        if (count(array_intersect($codes, $underlying)) === 0) {
            abort(422, 'Staff member is not assigned to this workspace.');
        }

        $ctx['subject_type'] = 'staff';
        $ctx['subject_id'] = $staff->id;
        $ctx['subject_name'] = (string) $staff->name;
        $ctx['filter_mode'] = 'staff';
        Session::put(self::SESSION_KEY, $ctx);

        $this->audit->logAdminAction($admin, 'admin.role_view.filter', $staff, [
            'role_key' => $workspaceKey,
            'filter_mode' => 'staff',
            'subject_id' => $staff->id,
        ]);

        return ['url' => $this->workspaceHomeUrl($workspaceKey)];
    }

    public function workspaceHomeUrl(string $workspaceKey): string
    {
        if (in_array($workspaceKey, [self::WORKSPACE_SUPPORT, ...self::SUPPORT_ROLE_KEYS], true)) {
            return app(\App\Services\Support\CustomerSupportWorkspaceService::class)->homeUrl();
        }

        $route = match ($workspaceKey) {
            'marketer' => 'admin.growth.index',
            'asset_manager' => 'admin.marketplace-assets.index',
            'officer', 'credit_analyst' => 'admin.teams.screening',
            'credit_committee' => 'admin.teams.committee',
            'manager' => 'admin.teams.management',
            'partner_support' => 'admin.teams.partners',
            'collector' => 'admin.reports.collections-performance',
            'auditor' => 'admin.audit-logs.index',
            default => 'admin.dashboard',
        };

        if (\Illuminate\Support\Facades\Route::has($route)) {
            return route($route);
        }

        return route('admin.dashboard');
    }

    /** @return list<array{id: int, name: string, subtitle: string}> */
    public function workspaceStaffOptions(?array $ctx = null): array
    {
        $ctx ??= $this->active();
        if (! $ctx) {
            return [];
        }

        $workspaceKey = (string) ($ctx['workspace_key'] ?? $ctx['role_key'] ?? '');
        $row = collect($this->staffRoleDirectory())->firstWhere('key', $workspaceKey)
            ?? collect($this->staffRoleDirectory())->first(
                fn ($r) => in_array($workspaceKey, $r['underlying_roles'] ?? [], true)
            );

        return $row['staff'] ?? [];
    }

    /**
     * Legacy person search kept for API compatibility; Account / Role UI uses staffRoleDirectory().
     *
     * @return list<array{
     *   subject_type: string,
     *   subject_id: int,
     *   name: string,
     *   subtitle: string,
     *   roles: list<array{key: string, label: string, enterable: bool}>,
     *   profile_url: string
     * }>
     */
    public function search(string $query, int $limit = 8): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
        $out = [];

        $staff = User::query()
            ->where(function ($w) use ($like, $q) {
                $w->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->filter(fn (User $u) => $this->roles->isStaffUser($u));

        foreach ($staff as $user) {
            $out[] = $this->serializeStaff($user);
        }

        return array_slice($out, 0, $limit);
    }

    /**
     * @return array{url: string}
     */
    public function profileUrl(string $subjectType, int $subjectId): array
    {
        return match ($subjectType) {
            'partner' => [
                'url' => route('admin.partners.show', $subjectId),
            ],
            'staff' => [
                'url' => route('admin.users.show', $subjectId),
            ],
            'borrower' => [
                'url' => route('admin.customers.show', $subjectId),
            ],
            default => throw new \InvalidArgumentException('Unknown subject type.'),
        };
    }

    /**
     * Enter a role workspace. Admin guard stays; partner portal uses web session only while viewing.
     *
     * @return array{url: string}
     */
    public function enter(User $admin, string $subjectType, int $subjectId, string $roleKey): array
    {
        if (! $admin->canAccessConsole()) {
            abort(403);
        }

        return match ($subjectType) {
            'partner' => $this->enterPartner($admin, $subjectId, $roleKey),
            'staff' => $this->enterStaff($admin, $subjectId, $roleKey),
            default => abort(422, 'Borrowers have View profile only — no workspace enter.'),
        };
    }

    public function exit(): string
    {
        $ctx = $this->active();
        $admin = Auth::guard('admin')->user();

        if ($ctx && $admin) {
            $this->audit->logAdminAction($admin, 'admin.role_view.exit', null, [
                'subject_type' => $ctx['subject_type'] ?? null,
                'subject_id' => $ctx['subject_id'] ?? null,
                'role_key' => $ctx['role_key'] ?? null,
            ]);
        }

        $this->clearWebPartnerSessionIfViewing($ctx);
        Session::forget(self::SESSION_KEY);
        Session::forget(PartnerWorkspaceService::SESSION_KEY);
        app(\App\Services\Support\CustomerSupportWorkspaceService::class)->clearSupportShell();

        return route('admin.dashboard');
    }

    public function actorForAudit(?User $fallback = null): ?User
    {
        $ctx = $this->active();
        if ($ctx) {
            $admin = Auth::guard('admin')->user();
            if ($admin) {
                return $admin;
            }
            if (! empty($ctx['admin_id'])) {
                return User::query()->find((int) $ctx['admin_id']) ?? $fallback;
            }
        }

        return Auth::guard('admin')->user() ?? $fallback ?? Auth::user();
    }

    public function blocksCredentialMutation(): bool
    {
        return $this->isActive();
    }

    private function enterPartner(User $admin, int $partnerId, string $roleKey): array
    {
        $partner = Partner::query()->findOrFail($partnerId);
        $assigned = $partner->partnerRoles();
        if (! in_array($roleKey, $assigned, true)
            && ! ($assigned === [] && $roleKey === (string) $partner->category)) {
            abort(422, 'Role is not assigned to this partner.');
        }

        // Never treat borrower/member as a Partner workspace.
        if (in_array($roleKey, ['borrower', 'member', 'customer'], true)) {
            abort(422, 'Borrower identity stays separate.');
        }

        $user = $partner->user;
        if (! $user) {
            abort(422, 'Partner has no portal login yet — use View profile.');
        }

        $workspaceKey = $this->partnerWorkspaces->workspaceKeyForRole($roleKey) ?? 'service';
        $url = $this->partnerWorkspaces->switchTo($partner, $workspaceKey);

        Session::put(self::SESSION_KEY, [
            'admin_id' => $admin->id,
            'subject_type' => 'partner',
            'subject_id' => $partner->id,
            'subject_name' => (string) ($partner->name ?: 'Partner'),
            'role_key' => $roleKey,
            'role_label' => $this->partnerWorkspaces->labelForRole($roleKey),
            'workspace_key' => $workspaceKey,
            'partner_user_id' => $user->id,
            'entered_at' => now()->toIso8601String(),
        ]);

        // Temporary portal viewing only — does not replace Admin auth or touch PIN/password.
        Auth::guard('web')->login($user, false);
        Session::put('account_welcome_done', true);

        $this->audit->logAdminAction($admin, 'admin.role_view.enter', $partner, [
            'subject_type' => 'partner',
            'role_key' => $roleKey,
            'workspace_key' => $workspaceKey,
            'mode' => 'viewing',
        ]);

        return ['url' => $url];
    }

    private function enterStaff(User $admin, int $userId, string $roleKey): array
    {
        // Prefer role-first: enter the workspace, then filter to this staff member.
        $result = $this->enterWorkspace($admin, $roleKey);
        $this->selectWorkspaceStaff($admin, $userId);

        return $result;
    }

    private function clearWebPartnerSessionIfViewing(?array $ctx): void
    {
        if (! $ctx || ($ctx['subject_type'] ?? '') !== 'partner') {
            return;
        }

        $web = Auth::guard('web')->user();
        $partnerUserId = (int) ($ctx['partner_user_id'] ?? 0);
        if ($web && $partnerUserId > 0 && (int) $web->id === $partnerUserId) {
            Auth::guard('web')->logout();
        }
    }

    /** @return array<string, mixed> */
    private function serializePartner(Partner $partner): array
    {
        $roles = [];
        foreach ($partner->partnerRoles() as $role) {
            if (in_array($role, ['borrower', 'member', 'customer'], true)) {
                continue;
            }
            $roles[] = [
                'key' => $role,
                'label' => $this->partnerWorkspaces->labelForRole($role),
                'enterable' => $partner->user_id !== null,
            ];
        }

        return [
            'subject_type' => 'partner',
            'subject_id' => $partner->id,
            'name' => (string) ($partner->name ?: 'Partner'),
            'subtitle' => trim(implode(' · ', array_filter([
                $partner->vendor_number,
                $partner->phone,
            ]))),
            'roles' => $roles,
            'profile_url' => route('admin.partners.show', $partner),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeStaff(User $user): array
    {
        $roles = [];
        foreach ($user->roleCodes() as $code) {
            if (! $this->roles->isStaffUser($user) && ! ($this->roles->definition($code)['staff'] ?? false)) {
                continue;
            }
            if (! ($this->roles->definition($code)['staff'] ?? false)) {
                continue;
            }
            $roles[] = [
                'key' => $code,
                'label' => $this->staffRoleLabel($code),
                'enterable' => true,
            ];
        }

        return [
            'subject_type' => 'staff',
            'subject_id' => $user->id,
            'name' => (string) $user->name,
            'subtitle' => $this->staffSubtitle($user),
            'roles' => $roles,
            'profile_url' => route('admin.users.show', $user),
        ];
    }

    private function staffSubtitle(User $user): string
    {
        $email = operator_email_display($user->email);
        if ($email !== '—') {
            return $email;
        }

        return (string) ($user->phone ?: '');
    }

    /** @return array<string, mixed> */
    private function serializeBorrower(Customer $customer): array
    {
        $name = (string) ($customer->full_name
            ?: trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))
            ?: 'Borrower');

        return [
            'subject_type' => 'borrower',
            'subject_id' => $customer->id,
            'name' => $name,
            'subtitle' => trim(implode(' · ', array_filter([
                $customer->phone,
                $customer->email,
            ]))),
            'roles' => [],
            'profile_url' => route('admin.customers.show', $customer),
        ];
    }
}
