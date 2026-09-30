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

    public function __construct(
        private PartnerWorkspaceService $partnerWorkspaces,
        private RoleService $roles,
        private AuditService $audit,
    ) {
    }

    public function active(): ?array
    {
        $ctx = Session::get(self::SESSION_KEY);
        if (! is_array($ctx) || empty($ctx['admin_id']) || empty($ctx['subject_type'])) {
            return null;
        }

        $admin = Auth::guard('admin')->user();
        if (! $admin || (int) $ctx['admin_id'] !== (int) $admin->id) {
            return null;
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

        $name = (string) ($ctx['subject_name'] ?? 'Person');
        $role = (string) ($ctx['role_label'] ?? $ctx['role_key'] ?? '');

        return trim($name.($role !== '' ? ' · '.$role : ''));
    }

    /**
     * Internal staff role directory for Account / Role.
     * Every configured staff role appears even when nobody is assigned.
     *
     * @return list<array{
     *   key: string,
     *   label: string,
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
                    'subtitle' => (string) ($user->email ?: $user->phone ?: ''),
                    'profile_url' => route('admin.users.show', $user),
                ];
            }
        }

        $rows = [];
        foreach ($roleCodes as $code) {
            $staff = array_values($byRole[$code]);
            $rows[] = [
                'key' => $code,
                'label' => $this->staffRoleLabel($code),
                'staff_count' => count($staff),
                'staff' => $staff,
            ];
        }

        return $rows;
    }

    public function staffRoleLabel(string $roleKey): string
    {
        return $roleKey === 'agent' ? 'Customer Support' : $this->roles->label($roleKey);
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
        $staff = User::query()->findOrFail($userId);
        if (! $this->roles->isStaffUser($staff)) {
            abort(422, 'Not a staff user.');
        }
        if (! $staff->hasRole($roleKey)) {
            abort(422, 'Role is not assigned to this staff member.');
        }

        Session::put(self::SESSION_KEY, [
            'admin_id' => $admin->id,
            'subject_type' => 'staff',
            'subject_id' => $staff->id,
            'subject_name' => (string) $staff->name,
            'role_key' => $roleKey,
            'role_label' => $this->staffRoleLabel($roleKey),
            'workspace_key' => $this->roles->deskCode($roleKey),
            'entered_at' => now()->toIso8601String(),
        ]);

        $this->audit->logAdminAction($admin, 'admin.role_view.enter', $staff, [
            'subject_type' => 'staff',
            'role_key' => $roleKey,
            'mode' => 'viewing',
        ]);

        // Staff workspaces live in Console — foundation lands on dashboard with banner.
        return ['url' => route('admin.dashboard')];
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
            'subtitle' => (string) ($user->email ?: $user->phone ?: ''),
            'roles' => $roles,
            'profile_url' => route('admin.users.show', $user),
        ];
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
