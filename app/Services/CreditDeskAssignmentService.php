<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreditDeskAssignmentService
{
    public const SCREENING_DEPT = 'UND';

    public const COMMITTEE_DEPT = 'CRC';

    public const MANAGEMENT_DEPT = 'CRM';

    /** Authorization queue: committee already decided, matrix requires Management. */
    public const MANAGEMENT_AUTHORIZATION_STAGES = ['awaiting_management'];

    /**
     * Post-final-approval ops that Credit Management runs after the offer is issued.
     *
     * @var list<string>
     */
    public const MANAGEMENT_OPS_STAGES = [
        'approval',
        'post_approval_fees',
        'awaiting_disbursement_details',
        'contract_generation',
        'disbursement',
    ];

    public const HEAD_OFFICE = 'HQ001';

    /** @var list<string> */
    public const SCREENING_ROLES = ['credit_analyst', 'officer'];

    /** @var list<string> */
    public const COMMITTEE_ROLES = ['credit_committee'];

    public function isExempt(?string $role): bool
    {
        return in_array($role, ['admin', 'super_admin'], true);
    }

    /** @return list<string> */
    public function departmentCodes(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $codes = collect();
        if ($user->department) {
            $codes->push($user->department->code);
        }

        $codes = $codes->merge($user->departments->pluck('code'));

        return $codes
            ->map(fn ($code) => strtoupper((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function onScreeningDesk(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($user->role, self::SCREENING_ROLES, true)
            || in_array(self::SCREENING_DEPT, $this->departmentCodes($user), true);
    }

    public function onCommitteeDesk(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($user->role, self::COMMITTEE_ROLES, true)
            || in_array(self::COMMITTEE_DEPT, $this->departmentCodes($user), true);
    }

    public function onManagementDesk(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->role === 'manager'
            || in_array(self::MANAGEMENT_DEPT, $this->departmentCodes($user), true);
    }

    public function isManagementOnly(?User $user): bool
    {
        if (! $user || $this->isExempt($user->role)) {
            return false;
        }

        return $this->onManagementDesk($user)
            && ! $this->onScreeningDesk($user)
            && ! $this->onCommitteeDesk($user);
    }

    /** Rejected files stay with screening and committee — not credit management. */
    public function canViewRejected(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isExempt($user->role)
            || $this->onScreeningDesk($user)
            || $this->onCommitteeDesk($user);
    }

    /**
     * Stages a management-only desk may see: committee-approved authorization, then post-approval ops.
     *
     * @return list<string>
     */
    public function managementVisibleStages(): array
    {
        return [
            ...self::MANAGEMENT_AUTHORIZATION_STAGES,
            ...self::MANAGEMENT_OPS_STAGES,
        ];
    }

    /**
     * Management is an authorization layer for committee-approved loans, not a screening queue.
     * They never receive files still in screening, still with committee, referred back, incomplete, or rejected.
     */
    public function canViewApplication(?User $user, LoanApplication $application): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isExempt($user->role)) {
            return true;
        }

        if ($application->isClosed() && $application->closedStatus() === 'rejected') {
            return $this->canViewRejected($user);
        }

        if (! $this->isManagementOnly($user)) {
            return true;
        }

        $stage = (string) ($application->current_stage ?? '');
        $status = (string) ($application->status ?? '');

        if (in_array($status, ['rejected', 'draft', 'awaiting_guarantor'], true)
            || $stage === 'rejected'
            || in_array($stage, ['submitted', 'screening', 'credit_appraisal', 'pre_approval', 'awaiting_guarantor'], true)) {
            return false;
        }

        if ($application->hasActiveFacility() || $status === 'disbursed') {
            return true;
        }

        return in_array($stage, $this->managementVisibleStages(), true);
    }

    /**
     * @param  list<int>  $departmentIds
     */
    public function assertCompatible(string $role, array $departmentIds, ?User $existing = null): void
    {
        if ($this->isExempt($role)) {
            return;
        }

        $codes = Department::query()
            ->whereIn('id', $departmentIds)
            ->pluck('code')
            ->map(fn ($code) => strtoupper((string) $code))
            ->filter()
            ->values()
            ->all();

        $hasScreeningDept = in_array(self::SCREENING_DEPT, $codes, true);
        $hasCommitteeDept = in_array(self::COMMITTEE_DEPT, $codes, true);
        $isScreeningRole = in_array($role, self::SCREENING_ROLES, true);
        $isCommitteeRole = in_array($role, self::COMMITTEE_ROLES, true);

        if ($hasScreeningDept && $hasCommitteeDept) {
            throw ValidationException::withMessages([
                'department_ids' => 'A user cannot be on both Screening (UND) and Committee (CRC). Choose one desk — Admin roles are exempt. Committee + Management is allowed.',
            ]);
        }

        if ($isScreeningRole && $hasCommitteeDept) {
            throw ValidationException::withMessages([
                'department_ids' => 'Screening roles cannot also join the Credit committee team. Pick Screening or Committee — not both.',
            ]);
        }

        if ($isCommitteeRole && $hasScreeningDept) {
            throw ValidationException::withMessages([
                'department_ids' => 'Committee roles cannot also join the Screening team. Pick Screening or Committee — not both.',
            ]);
        }

        if ($role === 'partner_support' && $hasScreeningDept) {
            throw ValidationException::withMessages([
                'department_ids' => 'Partner support cannot also join Screening. They enroll partners and set coverage; they do not screen loan files.',
            ]);
        }

        if ($role === 'partner_support' && $hasCommitteeDept) {
            throw ValidationException::withMessages([
                'department_ids' => 'Partner support cannot also join Committee. They enroll partners; they do not decide loans.',
            ]);
        }

        if ($isScreeningRole && $isCommitteeRole) {
            throw ValidationException::withMessages([
                'role' => 'A user cannot hold both a screening role and a committee role.',
            ]);
        }
    }

    public function headOfficeBranchId(): ?int
    {
        $id = Branch::query()->where('code', self::HEAD_OFFICE)->value('id')
            ?? Branch::query()->where('is_active', true)->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    public function defaultDepartmentCode(string $role): ?string
    {
        return app(RoleService::class)->deskCode($role);
    }

    public function defaultDepartmentId(string $role): ?int
    {
        $code = $this->defaultDepartmentCode($role);
        if (! $code) {
            return null;
        }

        $id = Department::query()->where('code', $code)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Role picks the desk. Extra teams from the form are kept, minus desks that conflict.
     *
     * @param  list<int>  $departmentIds
     * @return list<int>
     */
    public function ensureDesk(string $role, array $departmentIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $departmentIds)));
        $primary = $this->defaultDepartmentId($role);
        if ($primary && ! in_array($primary, $ids, true)) {
            $ids[] = $primary;
        }

        $blocked = $this->blockedExtraDepartmentCodes($role);
        if ($blocked !== []) {
            $blockedIds = Department::query()->whereIn('code', $blocked)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_filter($ids, fn (int $id) => ! in_array($id, $blockedIds, true) || $id === $primary));
        }

        return $ids;
    }

    public function primaryDepartmentId(string $role, array $departmentIds): ?int
    {
        return $this->defaultDepartmentId($role)
            ?: (isset($departmentIds[0]) ? (int) $departmentIds[0] : null);
    }

    /**
     * Department codes this role must not join (credit desk separation).
     *
     * @return list<string>
     */
    public function blockedExtraDepartmentCodes(string $role): array
    {
        if ($this->isExempt($role)) {
            return [];
        }

        if (in_array($role, self::SCREENING_ROLES, true)) {
            return [self::COMMITTEE_DEPT];
        }

        if (in_array($role, self::COMMITTEE_ROLES, true)) {
            return [self::SCREENING_DEPT];
        }

        if ($role === PartnerStaffService::ROLE) {
            return [self::SCREENING_DEPT, self::COMMITTEE_DEPT];
        }

        return [];
    }

    /**
     * Optional extra teams shown on the user form (the home desk is assigned automatically).
     *
     * @return array<int, string>
     */
    public function extraTeamOptions(string $role): array
    {
        $homeId = $this->defaultDepartmentId($role);
        $blocked = $this->blockedExtraDepartmentCodes($role);

        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->reject(function (Department $department) use ($homeId, $blocked): bool {
                if ($homeId && (int) $department->id === $homeId) {
                    return true;
                }

                return in_array(strtoupper((string) $department->code), $blocked, true);
            })
            ->mapWithKeys(fn (Department $department) => [(int) $department->id => $department->name])
            ->all();
    }
}
