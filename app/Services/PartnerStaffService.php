<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;

class PartnerStaffService
{
    public const ROLE = 'partner_support';

    public const DEPARTMENT = 'PRT';

    public function __construct(
        private readonly CreditDeskAssignmentService $desks,
        private readonly PermissionService $permissions,
    ) {}

    public function managesPartners(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (in_array($user->role, ['admin', 'super_admin', self::ROLE], true)) {
            return true;
        }

        if ($this->permissions->has($user, 'partners.manage')) {
            return true;
        }

        return in_array(self::DEPARTMENT, $this->desks->departmentCodes($user), true);
    }

    public function canNegotiateRates(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->permissions->has($user, 'settings.manage');
    }

    /** @return list<string> */
    public function duties(): array
    {
        return [
            'Watch Alerts and the dashboard for “Partner needed in …” when screening has a file with no valuer, GPS installer, or insurer in that region.',
            'Screen Partner applications: open the dossier and review everything they submitted (profile, coverage, identity, documents) before you approve.',
            'Open the coverage request and check existing partners first. If someone is based there or can take the work, add the region on their profile.',
            'Enroll a new partner only when nobody on the list fits. Set coverage, then portal access (invite, activate with PIN, or draft).',
            'Keep partner profiles current: phone, regions, rates, and portal PIN / activation.',
            'Look at Field & recovery and the asset marketplace so waiting jobs and listings stay matched.',
            'Do not screen, approve, or reject the loan. Open the credit file only to see why coverage was asked.',
        ];
    }

    public function departmentId(): ?int
    {
        $id = Department::query()->where('code', self::DEPARTMENT)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Partner support always belongs to the PRT team so nav stays on partners (not the full console).
     *
     * @param  list<int>  $departmentIds
     * @return list<int>
     */
    public function ensureTeam(string $role, array $departmentIds): array
    {
        if ($role !== self::ROLE) {
            return $departmentIds;
        }

        $prtId = $this->departmentId();
        if ($prtId && ! in_array($prtId, $departmentIds, true)) {
            $departmentIds[] = $prtId;
        }

        return array_values(array_unique(array_map('intval', $departmentIds)));
    }

    public function primaryDepartmentId(string $role, ?int $departmentId): ?int
    {
        if ($role !== self::ROLE) {
            return $departmentId ?: null;
        }

        return $departmentId ?: $this->departmentId();
    }

    public function policyMessage(string $action = 'manage partners'): string
    {
        return 'Only Partner support or an admin can '.$action.'.';
    }
}
