<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreditDeskAssignmentService
{
    public const SCREENING_DEPT = 'UND';

    public const COMMITTEE_DEPT = 'CRC';

    public const MANAGEMENT_DEPT = 'CRM';

    /** @var list<string> */
    public const SCREENING_ROLES = ['credit_analyst', 'officer'];

    /** @var list<string> */
    public const COMMITTEE_ROLES = ['credit_committee'];

    public function isExempt(?string $role): bool
    {
        return in_array($role, ['admin', 'super_admin'], true);
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

        if ($isScreeningRole && $isCommitteeRole) {
            throw ValidationException::withMessages([
                'role' => 'A user cannot hold both a screening role and a committee role.',
            ]);
        }
    }
}
